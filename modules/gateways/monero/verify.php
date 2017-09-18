<?php
include "../../../init.php"; 
include("../../../includes/functions.php");
include("../../../includes/gatewayfunctions.php");
include("../../../includes/invoicefunctions.php");
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$invoice_id = $_POST['invoice_id'];
$payment_id = $_POST['payment_id'];
$amount = $_POST['amount'];
$fee = "0.0";
//$tranaction = $_POST['tranaction_id'];
$gatewaymodule = "Monero";
require_once('library.php');
$monero_daemon = new Monero_rpc('localhost:18081/json_rpc');
function verify_payment($payment_id, $amount, $invoice_id, $fee){
      /* 
       * function for verifying payments
       * Check if a payment has been made with this payment id then notify the $
       */
       
$monero_daemon = new Monero_rpc('localhost:18081/json_rpc');
      $amount_atomic_units = $amount * 1000000000000;
      $get_payments_method = $monero_daemon->get_payments($payment_id);
      if(isset($get_payments_method["payments"][0]["amount"]))
      { 
       if($get_payments_method["payments"][0]["amount"] >= $amount_ato$
                {
                        $message = "Payment has been received and confirmed. ";
                         $confirmed = true;
                        $transid = "0000";
                        logTransaction("monero",array('successful' => '0'),"Successful");            
                        addInvoicePayment($invoice_id,$transid,$amount,$fee,"monero");
 }  
          }
          else
          {
                  $message = "We are waiting for your payment to be confirmed";
          }
          return $message;  
  }
$vefiry = verify_payment($payment_id, $amount, $invoice_id, $fee);
echo $vefiry;
