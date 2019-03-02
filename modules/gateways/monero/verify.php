<?php

include("../../../init.php"); 
include("../../../includes/functions.php");
include("../../../includes/gatewayfunctions.php");
include("../../../includes/invoicefunctions.php");

use Illuminate\Database\Capsule\Manager as Capsule;

$fee = "0.0";
$status = "unknown";
$gatewaymodule = "monero";
$GATEWAY = getGatewayVariables($gatewaymodule);

$_POST  = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
$invoice_id = $_POST['invoice_id'];
$payment_id = $_POST['payment_id'];
$amount_xmr = $_POST['amount_xmr'];
$amount = $_POST['amount'];
$hash = $_POST['hash'];
$currency = $_POST['currency'];
$client_id = $_POST['client_id'];

$secretKey = $GATEWAY['secretkey'];
$link = $GATEWAY['daemon_host'].":".$GATEWAY['daemon_port']."/json_rpc";

require_once('library.php');


function verify_payment($payment_id, $amount, $amount_xmr, $invoice_id, $fee, $status, $gatewaymodule, $hash, $secretKey, $currency, $client_id){
	global $currency_symbol;
	$monero_daemon = new Monero_rpc($link);
	$check_mempool = true;
	//Checks invoice ID is a valid invoice number 
	$invoice_id = checkCbInvoiceID($invoice_id, $gatewaymodule);

	if ($payment_id !="") {
		//Validate callback authenticity
		if ($hash != md5($invoice_id . $payment_id . $amount_xmr . $secretKey)) {
			return 'Hash Verification Failure';
		}
		$message = "Waiting for your payment.";


 		//payment_id is sometimes empty

		// send each monero tx in the mempool to handle_whmcs
		if ($check_mempool) {
			$get_payments_method = $monero_daemon->get_transfers('pool', true);
			foreach ($get_payments_method["pool"] as $tx => $transactions) {
				$txn_amt = $transactions["amount"];
				$txn_txid = $transactions["txid"];
				$txn_payment_id = $transactions["payment_id"];
				if(isset($txn_amt)) { 
					return handle_whmcs($invoice_id, $amount_xmr, $txn_amt, $txn_txid, $txn_payment_id, $payment_id, $currency, $gatewaymodule, $client_id);
				}
			}
		}
		// send each monero tx to handle_whmcs
		$get_payments_method = $monero_daemon->get_payments($payment_id);
		foreach ($get_payments_method["payments"] as $tx => $transactions) {
			$txn_amt = $transactions["amount"];
			$txn_txid = $transactions["tx_hash"];
			$txn_payment_id = $transactions["payment_id"];
			if(isset($txn_amt)) { 
				return handle_whmcs($invoice_id, $amount_xmr, $txn_amt, $txn_txid, $txn_payment_id, $payment_id, $currency, $gatewaymodule, $client_id);
			}
		}
	} else {
		return "Error: No payment ID.";
	}
	return $message;
}

function handle_whmcs($invoice_id, $amount_xmr, $txn_amt, $txn_txid, $txn_payment_id, $payment_id, $currency, $gatewaymodule, $client_id) {
	global $currency_symbol;
	$amount_atomic_units = $amount_xmr * 1000000000000;
	
	//check if monero tx already exists in whmcs 
	$record = Capsule::table('tblaccounts')->where('transid', $txn_txid)->get();
	$transaction_exists = $record[0]->transid;
	if ($txn_payment_id == $payment_id) {
		if (!$transaction_exists) {
			//check one more time then add the payment if the transaction has not been added.
			checkCbTransID($txn_txid);
			$fiat_paid = xmr_to_fiat($txn_amt, $currency);
			add_payment("AddInvoicePayment", $invoice_id, $txn_txid, $gatewaymodule, $fiat_paid, $txn_amt / 1000000000000, $payment_id, $fee, $client_id);
		}
		
		// add 3% when doing the comparison in case of price fluctuations?
		if ($txn_amt * 1.03 >= $amount_atomic_units) {
			// check if invoice has been marked as paid, if not, mark Paid.  WHMCS normally wont mark as Paid if the amount isnt at least exactly the invoice due amount, which would stop service deployments due to WHCMS thinking a few cents were missing.
			$command = 'GetInvoice';
			$postData = array(
				'invoiceid' => $invoice_id,
			);
			$results = localAPI($command, $postData, $adminUsername);
			if ($results['status'] == "Unpaid") {
				$postData = array(
					'action' => "UpdateInvoice",
					'invoiceid' => $invoice_id,
					'status' => "Paid",
				);
				$results = localAPI("UpdateInvoice", $postData, $adminUsername);
			}
			return "Payment has been received.";
		} else {
		
			//check invoice balance
			$command = 'GetInvoice';
			$postData = array(
				'invoiceid' => $invoice_id,
			);
			$results = localAPI($command, $postData, $adminUsername);
			$invoice_balance = $results['balance'];
			//		if invoice balance is below 25 cents mark as paid
			if ($invoice_balance <= ".25") {
				$postData = array(
					'action' => "UpdateInvoice",
					'invoiceid' => $invoice_id,
					'status' => "Paid",
				);
				$results = localAPI("UpdateInvoice", $postData, $adminUsername);
				return "Payment has been received.";
			}
			$money_balance = money_format('%i', $invoice_balance);
			$xmr_remaining = monero_changeto($money_balance, $currency);

			return "Error: We received " . $txn_amt / 1000000000000 . " XMR but the remaining balance is still $currency_symbol$money_balance. Please send the remaining $xmr_remaining XMR. Transaction ID: " . $txn_txid . ". Payment ID: " . $payment_id;
		}
	}
}


function add_payment($command, $invoice_id, $txn_txid, $gatewaymodule, $fiat_paid, $amount_xmr, $payment_id, $fee, $client_id) {
	$GATEWAY = getGatewayVariables($gatewaymodule);

	$postData = array(
		'action' => $command,
		'invoiceid' => $invoice_id,
		'transid' => $txn_txid,
		'gateway' => $gatewaymodule,
		'amount' => $fiat_paid,
		'amount_xmr' => $amount_xmr,
		'paymentid' => $payment_id,
		'fees' => $fee,
	);
	// Add the invoice payment - either line below would work
	// $results = localAPI($command, $postData, $adminUsername);
    	addInvoicePayment($invoice_id,$txn_txid,$fiat_paid,$fee,$gatewaymodule);
	logTransaction($gatewaymodule, $postData, "Success: ".$message);
	

	$bonus_percent = $GATEWAY['bonus_percentage'];

 	if ($bonus_percent > 0) {
		$command = 'AddCredit';
		$postData = array(
			'clientid' => $client_id,
			'description' => "Bonus Credit for paying with Monero on Invoice #$invoice_id via txid $txn_txid",
			'amount' => money_format('%i', $fiat_paid * ($bonus_percent / 100)),
		);
		$results = localAPI($command, $postData, $adminUsername);
	}	
}


/*
function stop_payment($payment_id, $amount, $invoice_id, $fee, $link){
	$verify = verify_payment($payment_id, $amount, $invoice_id, $fee, $link);
	if($verify){
		$message = "Payment has been received and confirmed.";
	}
	else{
		$message = "We are waiting for your payment to be confirmed";
	}
} */


$vefiry = verify_payment($payment_id, $amount, $amount_xmr, $invoice_id, $fee, $status, $gatewaymodule, $hash, $secretKey, $currency, $client_id);
echo $vefiry;
