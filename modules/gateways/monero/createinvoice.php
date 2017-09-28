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

$amount_xmr = stripslashes($_POST['amount_xmr']);
$amount = stripslashes($_POST['amount']);

$payment_id = stripslashes($_POST['payment_id']);
$invoice_id = stripslashes($_POST['invoice_id']);
$array_integrated_address = $monero_daemon->make_integrated_address($payment_id);
$address = $array_integrated_address['integrated_address'];
$uri  =  "monero:$address?amount=$amount_xmr?payment_id=$payment_id";

$secretKey = $GATEWAY['secretkey'];
$hash = md5($invoice_id . $payment_id . $amount_xmr . $secretKey);

echo  "<script src='https://code.jquery.com/jquery-3.2.1.min.js'></script>";
echo "<link rel='stylesheet' href='https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css'>";
echo "<title>Invoice</title>";
echo "<div class='container'>";
echo "<div class='alert alert-warning' id='message'>".$message."</div>";
echo "<div class='row'>
 <div class='col-sm-12 col-md-12 col-lg-12'>                      
 <div class='panel panel-default' id='PaymentBox_de3a227fb470475'>
 <div class='panel-body'>
 <div class='row'>
                  <div class='col-sm-12 col-md-12 col-lg-12'>
                              <h3> Monero Payment Box</h3>
                  </div>
                     <div class='col-sm-3 col-md-3 col-lg-3'>
 <img src='https://chart.googleapis.com/chart?cht=qr&chs=250x250&chl=" . $uri ."' class='img-responsive'>
                         </div>
          <div class='col-sm-9 col-md-9 col-lg-9' style='padding:10px;'>
    Send <b>".$amount_xmr." XMR</b> to<br/><input type='text'  class='form-control' value='" . $array_integrated_address['integrated_address']."'>
    or scan QR Code with your mobile device<br/><br/>
    <small>If you don't know how to pay with monero or you don't know what monero is, please go <a href='http://www.getmonero.org/'>here</a>. </small>
    </div>
    <div class='col-sm-12 col-md-12 col-lg-12'>
        </div>
                                                </div>
                                                 </div>
                                   
                              </div>
                    </div>
                </div>
</div>";
echo "<script> function verify(){ 
			$.ajax({ url : 'verify.php',
				type : 'POST', 
				data: { 'amount_xmr' : '".$amount_xmr."', 'payment_id' : '".$payment_id."', 'invoice_id' : '".$invoice_id."', 'amount' : '".$amount."', 'hash' : '".$hash."'}, 
				success: function(msg) {
					console.log(msg);
					$('#message').text(msg);
				},
				error: function(data) {
					console.log(data);
				} 
	
			}); 
} 
verify();
setInterval(function(){ verify()}, 5000);
</script>";
?>

