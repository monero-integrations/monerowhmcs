<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function monero_MetaData()
{
    return array(
        'DisplayName' => 'Monero Payment Gateway Module',
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
        )
    );
}

