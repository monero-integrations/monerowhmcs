<?php
if (!defined('WHMCS')) {
	die('This file cannot be accessed directly');
}

function monero_MetaData(): array {
	return [
		'DisplayName' => 'Monero',
		'APIVersion' => '1.1', // Use API Version 1.1
		'DisableLocalCredtCardInput' => true,
		'TokenisedStorage' => false,
	];
}
function monero_Config(): array {
	return [
		'FriendlyName' => ['Type' => 'System','Value' => 'Monero'],
		'address' => ['FriendlyName' => 'Monero Address','Type' => 'text','Size' => '94','Default' => '','Description' => 'Not used yet'],
		'secretkey' => ['FriendlyName' => 'Module Secret Key','Type' => 'text','Default' => '21ieudgqwhb32i7tyg','Description' => 'Enter a unique key to verify callbacks'],
		'daemon_host' => ['FriendlyName' => 'Wallet RPC Host','Type' => 'text','Default' => 'localhost','Description' => 'Connection settings for the Monero Wallet RPC daemon.'],
		'daemon_port' => ['FriendlyName' => 'Wallet RPC Port','Type'  => 'text','Default' => '18081','Description' => ''],
		'daemon_user' => ['FriendlyName' => 'Wallet RPC Username','Type'  => 'text','Default' => '','Description' => ''],
		'daemon_pass' => ['FriendlyName' => 'Wallet RPC Password','Type'  => 'text','Default' => '','Description' => ''],
		'discount_percentage' => ['FriendlyName' => 'Discount Percentage','Type'  => 'text','Default' => '0%','Description' => 'Percentage discount for paying with Monero.']
	];
}

/*
*
*  Get the current XMR price in several currencies
*
*  @param String $currencies  List of currency codes separated by comma
*
*  @return String  A json string in the format {"CURRENCY_CODE":PRICE}
*
*/
function monero_retrieve_price_list($currencies = 'BTC,USD,EUR,CAD,INR,GBP,BRL') {
	$source = 'https://min-api.cryptocompare.com/data/price?fsym=XMR&tsyms=' . $currencies . '&extraParams=monero_woocommerce';

	if (ini_get('allow_url_fopen')) {
		return file_get_contents($source);
	}

	if (!function_exists('curl_init')) {
		echo 'cURL not available.';

		return false;
	}

	$options = [
		CURLOPT_URL            => $source,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_TIMEOUT        => 20,
	];

	$ch = curl_init();
	curl_setopt_array($ch, $options);

	$xmr_price = curl_exec($ch);

	curl_close($ch);

	if ($xmr_price === false) {
		echo 'Error while retrieving XMR price list';
	}

	return $xmr_price;
}

function monero_retrieve_price($currency) {
	global $currency_symbol;
	$xmr_price = monero_retrieve_price_list('BTC,USD,EUR,CAD,INR,GBP,BRL');
	$price = json_decode($xmr_price, true);
	if (!isset($price)) {
		echo 'There was an error';
	}
	if ($currency == 'USD') {
		$currency_symbol = '$';

		return $price['USD'];
	}
	if ($currency == 'EUR') {
		$currency_symbol = '€';

		return $price['EUR'];
	}
	if ($currency == 'CAD') {
		$currency_symbol = '$';

		return $price['CAD'];
	}
	if ($currency == 'GBP') {
		$currency_symbol = '£';

		return $price['GBP'];
	}
	if ($currency == 'INR') {
		$currency_symbol = '₹';

		return $price['INR'];
	}
	if ($currency == 'BRL') {
		$currency_symbol = 'R$ ';

		return $price['BRL'];
	}
	if ($currency == 'XMR') {
		return '1';
	}
}

function monero_changeto($amount, $currency): float {
	$xmr_live_price = monero_retrieve_price($currency);
	$live_for_storing = $xmr_live_price * 100; //This will remove the decimal so that it can easily be stored as an integer
	$new_amount = $amount / $xmr_live_price;

	return round($new_amount, 12);
}

function xmr_to_fiat($amount, $currency): float {
	$xmr_live_price = monero_retrieve_price($currency);
	$amount = $amount / 1000000000000;
	$new_amount = $amount * $xmr_live_price;

	return round($new_amount, 2);
}

function monero_link($params) {
	global $currency_symbol;

	$gatewaymodule = 'monero';
	$gateway = getGatewayVariables($gatewaymodule);
	if (!$gateway['type']) {
		die('Module not activated');
	}

	$invoiceid = $params['invoiceid'];
	$amount = $params['amount'];
	$discount_setting = $gateway['discount_percentage'];
	$discount_percentage = 100 - (preg_replace('/[^0-9]/', '', $discount_setting));
	$amount = money_format('%i', $amount * ($discount_percentage / 100));
	$currency = $params['currency'];
	$client_id = $params['clientdetails']['id'];
	$firstname = $params['clientdetails']['firstname'];
	$phone = $params['clientdetails']['firstname'];
	$lastname = $params['clientdetails']['lastname'];
	$email = $params['clientdetails']['email'];
	$city = $params['clientdetails']['city'];
	$state = $params['clientdetails']['state'];
	$postcode = $params['clientdetails']['postcode'];
	$country = $params['clientdetails']['country'];
	$address = $params['address'];
	$systemurl = $params['systemurl'];
	// Transform Current Currency into Monero
	$amount_xmr = monero_changeto($amount, $currency);

	$post = [
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
		'currency'      => $currency,
		'client_id'      => $client_id
	];
	$form = '<form action="' . $systemurl . 'modules/gateways/monero/createinvoice.php" method="POST">';
	foreach ($post as $key => $value) {
		$form .= '<input type="hidden" name="' . $key . '" value = "' . $value . '" />';
	}
	$form .= '<input type="submit" value="' . $params['langpaynow'] . '" />';
	$form .= '</form>';
	$form .= '<p>' . $amount_xmr . ' XMR (' . $currency_symbol . $amount . ' ' . $currency . ')</p>';
	if ($discount_setting > 0) {
		$form .= '<p><small>Discount Applied: ' . preg_replace('/[^0-9]/', '', $discount_setting) . '% </small></p>';
	}

	return $form;
}
