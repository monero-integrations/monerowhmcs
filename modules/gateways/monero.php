<?php
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}



function monero_MetaData()
{
    return array(
        'DisplayName' => 'Monero',
        'APIVersion' => '1.1', // Use API Version 1.1
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    );
}
function monero_Config(){
	return array(
		'FriendlyName' => array('Type' => 'System','Value' => 'Monero'),
		'address' => array('FriendlyName' => 'Monero Address','Type' => 'text','Size' => '94','Default' => '','Description' => 'Not used yet'),
		'secretkey' => array('FriendlyName' => 'Module Secret Key','Type' => 'text','Default' => '21ieudgqwhb32i7tyg','Description' => 'Enter a unique key to verify callbacks'),
		'daemon_host' => array('FriendlyName' => 'Wallet RPC Host','Type' => 'text','Default' => 'localhost','Description' => 'Connection settings for the Monero Wallet RPC daemon.'),
		'daemon_port' => array('FriendlyName' => 'Wallet RPC Port','Type'  => 'text','Default' => '18081','Description' => ''),
		'daemon_user' => array('FriendlyName' => 'Wallet RPC Username','Type'  => 'text','Default' => '','Description' => ''),
		'daemon_pass' => array('FriendlyName' => 'Wallet RPC Password','Type'  => 'text','Default' => '','Description' => ''),
		'discount_percentage' => array('FriendlyName' => 'Discount Percentage','Type'  => 'text','Default' => '0%','Description' => 'Percentage discount for paying with Monero.')
    );
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

	// cryptocompare now returns 401 without an API key, so we use CoinGecko's free price endpoint.
	$source = 'https://api.coingecko.com/api/v3/simple/price?ids=monero&vs_currencies='.strtolower($currencies);

	return monero_http_get($source);

}

// GET with a User-Agent set. CoinGecko is behind Cloudflare and 403s requests that don't send one.
// Tries file_get_contents first, then falls back to curl: allow_url_fopen can be enabled while
// outbound fopen is still blocked (proxy setups). Warnings are silenced so a failed fetch can't
// break the pay page or the verify AJAX output; callers check for the false return. Returns the
// body, or false on failure.
function monero_http_get($url) {
	$ua = 'monerowhmcs/1.1 (+https://github.com/monero-integrations/monerowhmcs)';
	if (ini_get('allow_url_fopen')) {
		$context = stream_context_create(array('http' => array(
			'header'  => "User-Agent: $ua\r\nAccept: application/json\r\n",
			'timeout' => 10,
		)));
		$response = @file_get_contents($url, false, $context);
		if ($response !== false) {
			return $response;
		}
	}
	if (!function_exists('curl_init')) {
		return false;
	}
	$ch = curl_init();
	curl_setopt_array($ch, array(
		CURLOPT_URL            => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_CONNECTTIMEOUT => 10,
		CURLOPT_TIMEOUT        => 20,
		CURLOPT_USERAGENT      => $ua,
		CURLOPT_HTTPHEADER     => array('Accept: application/json'),
	));
	$response = @curl_exec($ch);
	return $response;
}

// Exchange rate cache, one timestamped entry per currency. Saves an HTTP round trip on every
// invoice render, keeps us under CoinGecko's rate limit, and holds the last good rate so a short
// feed outage doesn't break checkout. It only turns on once the admin has configured a real module
// secret; the shipped default is public, so it must not be used to key a temp-file cache.
function monero_price_cache_file() {
	$gateway = getGatewayVariables('monero');
	$secret = isset($gateway['secretkey']) ? $gateway['secretkey'] : '';
	if ($secret == '' || $secret == '21ieudgqwhb32i7tyg') {
		return null;
	}
	return sys_get_temp_dir() . '/monerowhmcs_' . hash('sha256', 'pricecache' . $secret) . '.json';
}

function monero_price_cache_get($vs, $max_age) {
	$file = monero_price_cache_file();
	if ($file === null) {
		return null;
	}
	$raw = @file_get_contents($file);
	$data = $raw ? json_decode($raw, true) : null;
	$now = time();
	if (isset($data[$vs]['v'], $data[$vs]['ts'])
		&& is_numeric($data[$vs]['v']) && $data[$vs]['v'] > 0
		&& is_numeric($data[$vs]['ts']) && $data[$vs]['ts'] <= $now
		&& ($now - $data[$vs]['ts']) < $max_age) {
		return $data[$vs]['v'];
	}
	return null;
}

function monero_price_cache_put($rates) {
	$file = monero_price_cache_file();
	if ($file === null) {
		return;
	}
	$raw = @file_get_contents($file);
	$data = $raw ? json_decode($raw, true) : array();
	if (!is_array($data)) {
		$data = array();
	}
	$now = time();
	foreach ($rates as $vs => $rate) {
		if (is_numeric($rate) && $rate > 0) {
			$data[$vs] = array('v' => $rate, 'ts' => $now);
		}
	}
	@file_put_contents($file, json_encode($data), LOCK_EX);
}

function monero_retrieve_price($currency) {
	global $currency_symbol;
	// CoinGecko uses lowercase keys, so map the code and its symbol here. Still returns a numeric rate.
	$symbols = array('USD' => '$', 'EUR' => '€', 'CAD' => '$', 'GBP' => '£', 'INR' => '₹', 'BRL' => 'R$ ', 'BTC' => '₿');
	$currency = strtoupper($currency);
	if ($currency == 'XMR') {
		return '1';
	}
	if (isset($symbols[$currency])) {
		$currency_symbol = $symbols[$currency];
	}
	$vs = strtolower($currency);

	// a rate under 90 seconds old is fresh enough for an invoice, so skip the round trip
	$cached = monero_price_cache_get($vs, 90);
	if ($cached !== null) {
		return $cached;
	}

	$xmr_price = monero_retrieve_price_list('btc,usd,eur,cad,inr,gbp,brl');
	$price = json_decode($xmr_price, TRUE);
	if (isset($price['monero']) && is_array($price['monero'])) {
		monero_price_cache_put($price['monero']);
		if (isset($price['monero'][$vs]) && $price['monero'][$vs] > 0) {
			return $price['monero'][$vs];
		}
	}
	// fallback for hosts whose datacenter IP CoinGecko blocks. Kraken's public ticker needs no key
	// and works from servers, but only covers the major pairs.
	$kraken_pairs = array('USD' => 'XMRUSD', 'EUR' => 'XMREUR', 'BTC' => 'XMRXBT');
	if (isset($kraken_pairs[$currency])) {
		$kr = monero_http_get('https://api.kraken.com/0/public/Ticker?pair=' . $kraken_pairs[$currency]);
		$kd = json_decode($kr, true);
		if (isset($kd['result']) && is_array($kd['result'])) {
			$row = reset($kd['result']);
			if (isset($row['c'][0]) && $row['c'][0] > 0) {
				monero_price_cache_put(array($vs => $row['c'][0]));
				return $row['c'][0];
			}
		}
	}
	// both feeds down: use the last good rate for up to 15 minutes so a blip doesn't take checkout
	// with it. After that, bail and let the caller error rather than price off a stale rate.
	$stale = monero_price_cache_get($vs, 900);
	if ($stale !== null) {
		return $stale;
	}
	return null;
}

// Return a plain decimal XMR amount at piconero precision. PHP prints small floats as 1.0E-5;
// checkout POST handling expects a decimal string, so canonicalize before the amount is signed.
function monero_format_xmr_amount($xmr) {
	$xmr = (float)$xmr;
	if (!is_finite($xmr) || $xmr <= 0) {
		return '0';
	}
	$atomic = round($xmr * 1000000000000);
	if (!is_finite($atomic) || $atomic <= 0 || $atomic >= PHP_INT_MAX) {
		return '0';
	}
	$atomic = (int)$atomic;
	$whole = intdiv($atomic, 1000000000000);
	$fraction = $atomic % 1000000000000;
	if ($fraction == 0) {
		return (string)$whole;
	}
	return $whole . '.' . rtrim(str_pad((string)$fraction, 12, '0', STR_PAD_LEFT), '0');
}

function monero_changeto($amount, $currency){
    $xmr_live_price = monero_retrieve_price($currency);
	// retrieve_price returns null when every source fails. Guard the divide so checkout shows 0
	// instead of a PHP 8 "float / null" fatal.
	if (!is_numeric($xmr_live_price) || $xmr_live_price <= 0) {
		return 0;
	}
	$live_for_storing = $xmr_live_price * 100; //This will remove the decimal so that it can easily be stored as an integer
	$new_amount = $amount / $xmr_live_price;
	return monero_format_xmr_amount($new_amount);
}

function xmr_to_fiat($amount, $currency){
    $xmr_live_price = monero_retrieve_price($currency);
    $amount = $amount / 1000000000000;
	if (!is_numeric($xmr_live_price) || $xmr_live_price <= 0) {
		return 0;
	}
	$new_amount = $amount * $xmr_live_price;
	$rounded_amount = round($new_amount, 2);
    return $rounded_amount;
}



function monero_link($params){
global $currency_symbol;

$gatewaymodule = "monero";
$gateway = getGatewayVariables($gatewaymodule);
if(!$gateway["type"]) die("Module not activated");


	$invoiceid = $params['invoiceid'];
	$amount = $params['amount'];
	$discount_setting = $gateway['discount_percentage'];
	$discount_percentage = 100 - (preg_replace("/[^0-9]/", "", $discount_setting));
	// money_format() was removed in PHP 8.0, which is what current WHMCS runs on. Format the
	// discounted amount as a plain decimal instead (no locale separators that could break parsing).
	$amount = number_format($amount * ($discount_percentage / 100), 2, '.', '');
	$currency = $params['currency'];
	$client_id = $params['clientdetails']['id'];
	$firstname = $params['clientdetails']['firstname'];
	$lastname = $params['clientdetails']['lastname'];
	$email = $params['clientdetails']['email'];
	$phone = $params['clientdetails']['phonenumber'];
	$city = $params['clientdetails']['city'];
	$state = $params['clientdetails']['state'];
	$postcode = $params['clientdetails']['postcode'];
	$country = $params['clientdetails']['country'];
	$address = $params['address'];
	$address1 = $params['clientdetails']['address1'];
	$address2 = $params['clientdetails']['address2'];
	$systemurl = $params['systemurl'];
    // Transform Current Currency into Monero
	$amount_xmr = monero_changeto($amount, $currency);
	// no rate means we cannot price the invoice. Showing a pay form for 0 XMR would sign the
	// verification hash for a zero amount, so refuse to render it and let the customer retry.
	if ($amount_xmr <= 0) {
		return '<p>The XMR exchange rate is temporarily unavailable. Please reload this page in a few minutes.</p>';
	}

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
        'currency'      => $currency,
		'client_id'      => $client_id
    );
	$form = '<form action="' . $systemurl . 'modules/gateways/monero/createinvoice.php" method="POST">';
    foreach ($post as $key => $value) {
        $form .= '<input type="hidden" name="' . $key . '" value = "' . $value .'" />';
    }
    $form .= '<input type="submit" value="' . $params['langpaynow'] . '" />';
    $form .= '</form>';
	$form .= '<p>'.$amount_xmr. " XMR (". $currency_symbol . $amount . " " . $currency .')</p>';
	if ($discount_setting > 0) {
		$form .='<p><small>Discount Applied: ' . preg_replace("/[^0-9]/", "", $discount_setting) . '% </small></p>';
	}
    return $form;
}
