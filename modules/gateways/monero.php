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
    $xmr_live_price = $this->retriveprice($currency);
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
  $address1 = $params['clientdetails']['address1'];
        $address2 = $params['clientdetails']['address2'];
        $city = $params['clientdetails']['city'];
        $state = $params['clientdetails']['state'];
        $postcode = $params['clientdetails']['postcode'];
        $country = $params['clientdetails']['country'];
$address = $params['address'];
  $systemurl = $params['systemurl'];
    // Transform Current Currency into 
$amount_xmr = monero_changeto($amount, $currency);

//$amount_xmr = $amount;
$payment_id = monero_payment_id();
$post = array(
        'invoiceId'     => $invoiceid,
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
        'payment_id'    => $payment_id
    );
$form = '<form action="' . $systemurl . '"/modules/gateways/monero/createinvoice.php" method="POST">';

    foreach ($post as $key => $value) {
        $form .= '<input type="hidden" name="' . $key . '" value = "' . $value .'" />';
    }
    $form .= '<input type="submit" value="' . $params['langpaynow'] . '" />';
    $form .= '</form>';
$form .= '<p>'.$amount_xmr.$currency.'</p>';
    return $form;
    }
