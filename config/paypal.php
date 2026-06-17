<?php
return [
    'enabled' => true,
    'sandbox' => true,
    'client_id' => getenv('PAYPAL_CLIENT_ID') ?: '',
    'client_secret' => getenv('PAYPAL_CLIENT_SECRET') ?: '',
    'currency' => 'USD',
    'amount' => '5.00',
];
