<?php
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}



function monero_MetaData()
{
    return array(
        'DisplayName' => 'Monero',
        'APIVersion' => '1.1', // Use API Version 1.1
        'DisableLocalCredtCardInput' => true,
        'TokenisedStorage' => false,
    );
}
function monero_Config(){
	return array(
		'FriendlyName' => array('Type' => 'System','Value' => 'Monero Payment Gateway'),
		'address' => array('FriendlyName' => 'Monero Address','Type' => 'text','Size' => '94','Default' => '','Description' => 'Monero Address'),
		'secretkey' => array('FriendlyName' => 'Module Secret Key','Type' => 'text','Default' => '21ieudgqwhb32i7tyg','Description' => 'Enter a unique key to verify callbacks'),
		'daemon_host' => array('FriendlyName' => 'Wallet RPC Host','Type' => 'text','Default' => 'localhost','Description' => 'Wallet RPC Host'),
		'daemon_port' => array('FriendlyName' => 'Wallet RPC Port','Type'  => 'text','Default' => '18081','Description' => 'Wallet RPC Port')
    );
}


function monero_retriveprice($currency) {
	global $currency_symbol;
    $xmr_price = file_get_contents('https://min-api.cryptocompare.com/data/price?fsym=XMR&tsyms=BTC,USD,EUR,CAD,INR,GBP&extraParams=monero_woocommerce');
	$price = json_decode($xmr_price, TRUE);
	if(!isset($price)){
		echo "There was an error";
	}
	if ($currency == 'USD') {
		$currency_symbol = "$";
		return $price['USD'];
	}
	if ($currency == 'EUR') {
		$currency_symbol = "€";
		return $price['EUR'];
	}
	if ($currency == 'CAD'){
		$currency_symbol = "$";
		return $price['CAD'];
	}
	if ($currency == 'GBP'){
		$currency_symbol = "£";
		return $price['GBP'];
	}
	if ($currency == 'INR'){
		$currency_symbol = "₹";
		return $price['INR'];
	}
	if($currency == 'XMR'){
		$price = '1';
		return $price;
	}
}

function monero_changeto($amount, $currency){
    $xmr_live_price = monero_retriveprice($currency);
	$live_for_storing = $xmr_live_price * 100; //This will remove the decimal so that it can easily be stored as an integer
	$new_amount = $amount / $xmr_live_price;
	$rounded_amount = round($new_amount, 12);
    return $rounded_amount;
}

function xmr_to_fiat($amount, $currency){
    $xmr_live_price = monero_retriveprice($currency);
    $amount = $amount / 1000000000000;
	$new_amount = $amount * $xmr_live_price;
	$rounded_amount = round($new_amount, 2);
    return $rounded_amount;
}

function monero_payment_id(){
    if(!isset($_COOKIE['payment_id'])) { 
		$payment_id  = bin2hex(openssl_random_pseudo_bytes(8));
		setcookie('payment_id', $payment_id, time()+2700);
	} else {
		$payment_id = $_COOKIE['payment_id'];
		return $payment_id;
	}
}

function monero_link($params){
global $currency_symbol;
	$invoiceid = $params['invoiceid'];
	$amount = $params['amount'];
	$currency = $params['currency'];
	$firstname = $params['clientdetails']['firstname'];
	$lastname = $params['clientdetails']['lastname'];
	$email = $params['clientdetails']['email'];
	$city = $params['clientdetails']['city'];
	$state = $params['clientdetails']['state'];
	$postcode = $params['clientdetails']['postcode'];
	$country = $params['clientdetails']['country'];
	//$address = $params['address'];
	$systemurl = $params['systemurl'];
    // Transform Current Currency into Monero
	$amount_xmr = monero_changeto($amount, $currency);
	$payment_id = monero_payment_id();
	$post = array(
        'invoice_id'    => $invoiceid,
        'systemURL'     => $systemurl,
        'buyerName'     => $firstname . ' ' . $lastname,
        'buyerAddress1' => $address1,
        'buyerAddress2' => $address2,
        'buyerCity'     => $city,
        'buyerState'    => $state,
        'buyerZip'      => $postcode,
        'buyerEmail'    => $email,
        'buyerPhone'    => $phone,
        'address'       => $address,
        'amount_xmr'    => $amount_xmr,
        'amount'        => $amount,
        'payment_id'    => $payment_id,
        'currency'      => $currency     
    );
	$form = '<form action="' . $systemurl . 'modules/gateways/monero/createinvoice.php" method="POST">';
    foreach ($post as $key => $value) {
        $form .= '<input type="hidden" name="' . $key . '" value = "' . $value .'" />';
    }
    $form .= '<input type="submit" value="' . $params['langpaynow'] . '" />';
    $form .= '</form>';
	$form .= '<p>'.$amount_xmr. " XMR (". $currency_symbol . $amount . " " . $currency .')</p>';
    return $form;
}
