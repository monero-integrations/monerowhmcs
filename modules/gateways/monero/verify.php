<?php
include "../../../init.php"; 
include("../../../includes/functions.php");
include("../../../includes/gatewayfunctions.php");
include("../../../includes/invoicefunctions.php");
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

$verify = verify_payment($payment_id, $amount, $amount_xmr, $invoice_id, $fee, $status, $gatewaymodule, $hash, $secretKey);
echo $verify;


function verify_payment($payment_id, $amount, $amount_xmr, $invoice_id, $fee, $status, $gatewaymodule, $hash, $secretKey){

      /* 
       * function for verifying payments
       * Check if a payment has been made with this payment id then notify the $
       */
	$monero_daemon = new Monero_rpc($link);
	$amount_atomic_units = $amount_xmr * 1000000000000;

	$check_mempool = true;

	//Checks invoice ID is a valid invoice number 
	$invoice_id = checkCbInvoiceID($invoice_id, $gatewaymodule);

	//Validate callback authenticity
	if ($hash != md5($invoice_id . $payment_id . $amount_xmr . $secretKey)) {
		$transactionStatus = 'Hash Verification Failure';
		return $transactionStatus;
	}
	

	if (isset($payment_id)) {
	
		if ($check_mempool) {
			$get_payments_method = $monero_daemon->get_transfers('pool', true);
			foreach ($get_payments_method["pool"] as $tx => $transactions) {
				$txn_amt = $transactions["amount"];
				$txn_txid = $transactions["txid"];
				$txn_payment_id = $transactions["payment_id"];
				if(isset($txn_amt)) { 
					if ($txn_payment_id == $payment_id) {
						if($txn_amt >= $amount_atomic_units) {
							// Performs a check for any existing transactions with the same given transaction number.
							checkCbTransID($txn_txid);
							add_payment("AddInvoicePayment", $invoice_id, $txn_txid, $gatewaymodule, $amount, $amount_xmr, $payment_id, $fee);
						} else {
							$messages = "Amount too small";
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
				if ($txn_payment_id == $payment_id) {
					if($txn_amt >= $amount_atomic_units) {
						// Performs a check for any existing transactions with the same given transaction number.
						checkCbTransID($txn_txid);
						add_payment("AddInvoicePayment", $invoice_id, $txn_txid, $gatewaymodule, $amount, $amount_xmr, $payment_id, $fee);
					} else {
						$messages = "Amount too small";
					}
				}
			}
		}
		
	} else {
		$message = "We are waiting for your payment to be confirmed.";
		$transactionStatus = "unknown";

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
	logTransaction($gatewaymodule, $postData, "Success");
	$message = "Payment has been received and confirmed.";
	$transactionStatus = "confirmed";
}



