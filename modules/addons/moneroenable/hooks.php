<?php

if (!defined("WHMCS"))
	die("This file cannot be accessed directly");

use Illuminate\Database\Capsule\Manager as Capsule;

// skip checking for fraud order
function moneroEnable ( $vars ) {
	$opt1 = Capsule::select("SELECT `value` FROM tbladdonmodules WHERE module = 'moneroEnable' AND setting = 'option1' LIMIT 1")[0]->value;
	$opt2 = Capsule::select("SELECT `value` FROM tbladdonmodules WHERE module = 'moneroEnable' AND setting = 'option2' LIMIT 1")[0]->value;
	if($opt1 == 'on' && $opt2 > '' && $vars['orderid'] > '') {
		$pmtMet = Capsule::select("SELECT paymentmethod FROM tblorders WHERE id = ".$vars['orderid'])[0]->paymentmethod;
		if($pmtMet > '') {
			if($pmtMet == $opt2) return true;
		}
	}
}

add_hook("RunFraudCheck", 1, "moneroEnable");

?>
