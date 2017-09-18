<?php
include "../../../init.php"; 
include("../../../includes/functions.php");
include("../../../includes/gatewayfunctions.php");
include("../../../includes/invoicefunctions.php");
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$gatewaymodule = "monero";
$GATEWAY = getGatewayVariables($gatewaymodule);
if(!$GATEWAY["type"]) die("Module not activated");
require_once('library.php');

$link = $GATEWAY['daemon_host'].":".$GATEWAY['daemon_port']."/json_rpc";
$monero_daemon = new Monero_rpc($link);
$message = "Waiting for Payment confirmation";
$address = strip_slashes($_POST['address']);
$amount = strip_slashes($_POST['amount_xmr']);
$payment_id = strip_slashes($_POST['payment_id']);
$uri  =  "monero:$address?amount=$amount?payment_id=$payment_id";
$invoice_id = strip_slashes($_POST['invoice_id']);
$array_integrated_address = $monero_daemon->make_integrated_address($payment_id);
echo  "<script src='https://code.jquery.com/jquery-3.2.1.min.js'></script>";
echo "<link rel='stylesheet' href='https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css'>";
echo "<title>Invoice</title>";
echo "<div class='container'>";
echo "<div class='alert alert-warning' id='message'>".$message."</div>";
echo "<div class='row'>
 <div class='col-sm-12 col-md-12 col-lg-12'>                      
 <div class='panel panel-default' id='PaymentBox_de3a227fb470475'>

