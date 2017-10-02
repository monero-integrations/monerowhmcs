<?php

include("../../../init.php"); 
include("../../../includes/functions.php");
include("../../../includes/gatewayfunctions.php");
include("../../../includes/invoicefunctions.php");


$fee = "0.0";
$status = "unknown";
$gatewaymodule = "monero";
$GATEWAY = getGatewayVariables($gatewaymodule);

$invoice_id = $_POST['invoice_id'];
$payment_id = $_POST['payment_id'];
$amount_xmr = $_POST['amount_xmr'];
$amount = $_POST['amount'];
$hash = $_POST['hash'];

$secretKey = $GATEWAY['secretkey'];
$link = $GATEWAY['daemon_host'].":".$GATEWAY['daemon_port']."/json_rpc";

require_once('library.php');



function verify_payment($payment_id, $amount, $amount_xmr, $invoice_id, $fee, $status, $gatewaymodule, $hash, $secretKey){
global $currency_symbol;
      /* 
       * function for verifying payments
       * Check if a payment has been made with this payment id then notify the $
       */
	$monero_daemon = new Monero_rpc($link);
	$amount_atomic_units = $amount_xmr * 1000000000000;

	$check_mempool = true;

	//Checks invoice ID is a valid invoice number 
	$invoice_id = checkCbInvoiceID($invoice_id, $gatewaymodule);

	if ($payment_id !="") {
		//Validate callback authenticity
		if ($hash != md5($invoice_id . $payment_id . $amount_xmr . $secretKey)) {
			return 'Hash Verification Failure';
		}
	
		$message = "Waiting for your payment.";
	
		if ($check_mempool) {
			$get_payments_method = $monero_daemon->get_transfers('pool', true);
			foreach ($get_payments_method["pool"] as $tx => $transactions) {
				$txn_amt = $transactions["amount"];
				$txn_txid = $transactions["txid"];
				$txn_payment_id = $transactions["payment_id"];
				if(isset($txn_amt)) {
					$transaction_exists = mysql_fetch_array(mysql_query("SELECT * FROM `tblaccounts` WHERE transid = '$txn_txid'"));
					if ($txn_payment_id == $payment_id) {
					return $payment_id;
						if ($transaction_exists) {
							if ($txn_amt >= $amount_atomic_units) {
								return "Payment has been received.";
							} else {
								return "Error: Amount " . $txn_amt / 1000000000000 . " XMR too small. Please send full amount or contact customer service. Transaction ID: " . $txn_txid . ".";
							}
						} else {
							//check one more time then add the payment if the transaction has not been added.
							checkCbTransID($txn_txid);
							//find out how much local fiat was received.
							$fiat_paid = xmr_to_fiat($txn_amt * 1000000000000, $currency);
							add_payment("AddInvoicePayment", $invoice_id, $txn_txid, $gatewaymodule, $fiat_paid, $txn_amt / 1000000000000, $payment_id, $fee);
							if ($txn_amt >= $amount_atomic_units) {
								return "Payment has been received.";
							} else {
								return "Error: Amount " . $txn_amt / 1000000000000 . " XMR too small. Please send full amount or contact customer service. Transaction ID: " . $txn_txid . ".";
							}
						}
					}
				}
			}
		}		
		$get_payments_method = $monero_daemon->get_payments($payment_id);
		foreach ($get_payments_method["payments"] as $tx => $transactions) {
			$txn_amt = $transactions["amount"];
			$txn_txid = $transactions["tx_hash"];
			$txn_payment_id = $transactions["payment_id"];
			if(isset($txn_amt)) { 
				$transaction_exists = mysql_fetch_array(mysql_query("SELECT * FROM `tblaccounts` WHERE transid = '$txn_txid'"));
				if ($txn_payment_id == $payment_id) {
									return $payment_id;
					if ($transaction_exists) {
						if ($txn_amt >= $amount_atomic_units) {
							return "Payment has been received.";
						} else {
							return "Error: Amount " . $txn_amt / 1000000000000 . " XMR too small. Please send full amount or contact customer service. Transaction ID: " . $txn_txid . ".";
						}
					} else {
						//check one more time then add the payment if the transaction has not been added.
						checkCbTransID($txn_txid);
						$fiat_paid = xmr_to_fiat($txn_amt * 1000000000000, $currency);
						add_payment("AddInvoicePayment", $invoice_id, $txn_txid, $gatewaymodule, $fiat_paid, $txn_amt / 1000000000000, $payment_id, $fee);
						if ($txn_amt >= $amount_atomic_units) {
							return "Payment has been received.";
						} else {
							return "Error: Amount " . $txn_amt / 1000000000000 . " XMR too small. Please send full amount or contact customer service. Transaction ID: " . $txn_txid . ".";
						}
					}
				}
			}
		}
	} else {
		return "Error: No payment ID.";
	}
	return $message;
}

function add_payment($command, $invoice_id, $txn_txid, $gatewaymodule, $amount, $amount_xmr, $payment_id, $fee) {
	$postData = array(
		'action' => $command,
		'invoiceid' => $invoice_id,
		'transid' => $txn_txid,
		'gateway' => $gatewaymodule,
		'amount' => $amount,
		'amount_xmr' => $amount_xmr,
		'paymentid' => $payment_id,
		'fees' => $fee,
	);
	$results = localAPI($command, $postData, $adminUsername);
	logTransaction($gatewaymodule, $postData, "Success: ".$message);
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

$vefiry = verify_payment($payment_id, $amount, $amount_xmr, $invoice_id, $fee, $status, $gatewaymodule, $hash, $secretKey);
echo $vefiry;
