<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$GATEWAY = getGatewayVariables('monero');
require_once('library.php');
$monero_daemon = new Monero_rpc('localhost:18081/json_rpc');

$address = $_POST['address'];
$amount = $_POST['amount_xmr'];
$payment_id = $_POST['payment_id'];
$uri  =  "monero:$address&amount=$amount&payment_id=$payment_id";
