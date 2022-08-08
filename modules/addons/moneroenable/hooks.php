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

function monero_auto_withdrawal($vars) {
	$gatewaymodule = "monero";
	$GATEWAY = getGatewayVariables($gatewaymodule);
	if(!$GATEWAY["type"]) die("Module not activated");
	$library_path = (dirname(__DIR__, 3) . '/gateways/monero/library.php');
	require_once($library_path);
	$withdrawal_address = $GATEWAY['address'];
	if (!empty($withdrawal_address)) {
		$link = $GATEWAY['daemon_host'].":".$GATEWAY['daemon_port']."/json_rpc";
		$monero_daemon = new Monero_rpc($link);
		$monero_daemon->sweep_all($withdrawal_address);
	}
}

add_hook('AfterCronJob', 9, 'monero_auto_withdrawal');

?>
