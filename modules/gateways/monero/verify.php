<?php

include("../../../init.php"); 
include("../../../includes/functions.php");
include("../../../includes/gatewayfunctions.php");
include("../../../includes/invoicefunctions.php");
// xmr_to_fiat() / monero_retrieve_price() are defined in the gateway module. verify.php is hit
// directly via AJAX, so WHMCS does not auto-load them; include the module or crediting a detected
// payment fatals with "Call to undefined function xmr_to_fiat()".
require_once(__DIR__ . '/../monero.php');

use Illuminate\Database\Capsule\Manager as Capsule;

$fee = "0.0";
$status = "unknown";
$gatewaymodule = "monero";
$GATEWAY = getGatewayVariables($gatewaymodule);

$_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$invoice_id = $_POST['invoice_id'];
$payment_id = $_POST['payment_id'];
$amount_xmr = $_POST['amount_xmr'];
$amount = $_POST['amount'];
$hash = $_POST['hash'];
$currency = $_POST['currency'];


$secretKey = $GATEWAY['secretkey'];
$link = $GATEWAY['daemon_host'].":".$GATEWAY['daemon_port']."/json_rpc";

require_once('library.php');


function verify_payment($payment_id, $amount, $amount_xmr, $invoice_id, $fee, $status, $gatewaymodule, $hash, $secretKey, $currency){
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

		// detection talks to the wallet rpc/daemon; a transient outage must not 500 the customer's
		// polling page, so wrap only the RPC calls and stay in the waiting state on any RPC error.
		$get_transfers_method = array();
		$get_payments_method = array();
		try {
		// send each monero tx in the mempool to handle_whmcs
		if ($check_mempool) {
			$get_transfers_method = $monero_daemon->get_transfers('pool', true);
		}
		// send each monero tx to handle_whmcs
		$get_payments_method = $monero_daemon->get_payments($payment_id);
		} catch (\Throwable $e) {
			// keep the customer poll in the waiting state, but record the real failure for the admin
			if (function_exists('logTransaction')) {
				logTransaction($gatewaymodule, array(
					'invoice_id' => $invoice_id,
					'payment_id' => $payment_id,
					'error' => $e->getMessage(),
				), 'Payment verification error');
			}
		}
		if ($check_mempool) {
			// the wallet omits "pool"/"payments" entirely when empty; ?? [] avoids foreach(null)
			foreach (($get_transfers_method["pool"] ?? []) as $tx => $transactions) {
				$txn_amt = $transactions["amount"];
				$txn_txid = $transactions["txid"];
				$txn_payment_id = $transactions["payment_id"];
				if(isset($txn_amt)) { 
					return handle_whmcs($invoice_id, $amount_xmr, $txn_amt, $txn_txid, $txn_payment_id, $payment_id, $currency, $gatewaymodule);
				}
			}
		}
		foreach (($get_payments_method["payments"] ?? []) as $tx => $transactions) {
			$txn_amt = $transactions["amount"];
			$txn_txid = $transactions["tx_hash"];
			$txn_payment_id = $transactions["payment_id"];
			if(isset($txn_amt)) { 
				return handle_whmcs($invoice_id, $amount_xmr, $txn_amt, $txn_txid, $txn_payment_id, $payment_id, $currency, $gatewaymodule);
			}
		}
	} else {
		return "Error: No payment ID.";
	}
	return $message;
}

function handle_whmcs($invoice_id, $amount_xmr, $txn_amt, $txn_txid, $txn_payment_id, $payment_id, $currency, $gatewaymodule) {
	$fee = "0.0"; // not scoped in from verify_payment; default it so add_payment has a value (PHP 8 warns on undefined)
	$amount_atomic_units = $amount_xmr * 1000000000000;
	
	//check if monero tx already exists in whmcs 
	// use first() so a no-match returns null. on PHP 8 the old $record[0]->transid threw a fatal
	// (undefined array key + property on null) whenever the transaction was not already recorded.
	$record = Capsule::table('tblaccounts')->where('transid', $txn_txid)->first();
	$transaction_exists = $record ? $record->transid : null;
	if ($txn_payment_id == $payment_id) {
		if (!$transaction_exists) {
			//check one more time then add the payment if the transaction has not been added.
			checkCbTransID($txn_txid);
			$fiat_paid = xmr_to_fiat($txn_amt, $currency);
			// if the feed is down, crediting now records 0 fiat and the transid guard above means it
			// never gets corrected. Skip and let the next poll retry once the feed is back.
			if (!is_numeric($fiat_paid) || $fiat_paid <= 0) {
				return "Waiting for your payment.";
			}
			add_payment("AddInvoicePayment", $invoice_id, $txn_txid, $gatewaymodule, $fiat_paid, $txn_amt / 1000000000000, $payment_id, $fee);
		}
		// add 2% when doing the comparison in case of price fluctuations?
		if ($txn_amt * 1.02 >= $amount_atomic_units) {
			return "Payment has been received.";
		} else {
			return "Error: Amount " . $txn_amt / 1000000000000 . " XMR too small. Please send full amount or contact customer service. Transaction ID: " . $txn_txid . ". Payment ID: " . $payment_id;
		}
	}
}


function add_payment($command, $invoice_id, $txn_txid, $gatewaymodule, $fiat_paid, $amount_xmr, $payment_id, $fee) {
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
	// Add the invoice payment - either of the next two lines work
	// $results = localAPI($command, $postData, $adminUsername);
    	addInvoicePayment($invoice_id,$txn_txid,$fiat_paid,$fee,$gatewaymodule);
	// $message is not in scope here; log a plain status (PHP 8 warns on the undefined variable)
	logTransaction($gatewaymodule, $postData, "Success");
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

$verify = verify_payment($payment_id, $amount, $amount_xmr, $invoice_id, $fee, $status, $gatewaymodule, $hash, $secretKey, $currency);
echo $verify;
