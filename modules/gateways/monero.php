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
       
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Monero Payment Gateway',
        ),
        'address' => array(
            'FriendlyName' => 'Monero Address',
            'Type' => 'text',
            'Size' => '94',
            'Default' => '',
            'Description' => 'Monero Address',
        ),
        'secretkey' => array(
            'FriendlyName' => 'Module Secret Key',
            'Type' => 'text',
            'Default' => '21ieudgqwhb32i7tyg',
            'Description' => 'Enter a unique key to verify callbacks',
        ),
     'daemon_host' => array(
                'FriendlyName' => 'Daemon Host',
                'Type' => 'text',
                'Default' => 'localhost',
                'Description' => 'Daemon Host',
),
        'daemon_port' => array(
                'FriendlyName' => 'Daemon Port',
                'Type'  => 'text',
                'Default' => '18081',
                'Description' => 'Daemon Port'
            )
    );
}

function monero_retriveprice($currency)
                                {
    $xmr_price = file_get_contents('https://min-api.cryptocompare.com/data/price?fsym=XMR&tsyms=BTC,USD,EUR,CAD,INR,GBP&extraParams=monero_woocommerce');
								$price         = json_decode($xmr_price, TRUE);
								if(!isset($price)){
									echo "There was an error";
								}
								if ($currency == 'USD') {
												return $price['USD'];
								}
								if ($currency == 'EUR') {
												return $price['EUR'];
								}
								if ($currency == 'CAD'){
												return $price['CAD'];
								}
								if ($currency == 'GBP'){
												return $price['GBP'];
								}
								if ($currency == 'INR'){
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
								$new_amount     = $amount / $xmr_live_price;
								$rounded_amount = round($new_amount, 12);
    return $rounded_amount;
}

function monero_payment_id(){
    if(!isset($_COOKIE['payment_id']))
					{ 
						$payment_id  = bin2hex(openssl_random_pseudo_bytes(8));
						setcookie('payment_id', $payment_id, time()+2700);
					}
					else
						$payment_id = $_COOKIE['payment_id'];
					return $payment_id;
}

function monero_link($params){
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
    // Transform Current Currency into 
$amount_xmr = monero_changeto($amount, $currency);

switch ($currency) {
    case "USD":
        $currency_symbol = "$";
        break;
    case "EUR":
        $currency_symbol = "";
        break;
    case "CAD":
        $currency_symbol = "$";
        break;
}

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
        'payment_id'    => $payment_id
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
