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

$array_integrated_address = $monero_daemon->make_integrated_address($payment_id);
echo "<title>Invoice</title>";

echo "<div class='row'>
				
									<div class='col-sm-12 col-md-12 col-lg-12'>
				                        <div class='panel panel-default' id='PaymentBox_de3a227fb470475'>
        			                         <div class='panel-body'>
				                                <div class='row'>
					                               <div class='col-sm-12 col-md-12 col-lg-12'>
						                                  <h3> Monero Payment Box</h3>
					                               </div>
					                           <div class='col-sm-3 col-md-3 col-lg-3'>
						                          <img src='https://chart.googleapis.com/chart?cht=qr&chs=250x250&chl=" . $uri . "' class='img-responsive'>
					                           </div>
					                           <div class='col-sm-9 col-md-9 col-lg-9' style='padding:10px;'>
						                          Send <b>" . $amount_xmr2 . " XMR</b> to<br/><input type='text'  class='form-control' value='" . $array_integrated_address["integrated_address"]."'>
                                                or scan QR Code with your mobile device<br/><br/>
                                                <small>If you don't know how to pay with monero or you don't know what monero is, please go <a href='#'>here</a>. </small>
					                           </div>
					                           <div class='col-sm-12 col-md-12 col-lg-12'>
				
						                      
					                       </div>
				                        </div>
			                         </div>
                                   
		              </div>
                    </div>
                </div>
              ";
