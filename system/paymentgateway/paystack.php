<?php

/**
 *  PHP Mikrotik Billing (https://github.com/hotspotbilling/phpnuxbill/)
 *  by https://t.me/ibnux
 *  Paystack Payment Gateway with M-Pesa Integration
 **/

/**
 * Displays the configuration form for Paystack
 */
function paystack_show_config()
{
    global $ui;
    $ui->assign('_title', 'Paystack Configuration');

    $config = ORM::for_table('tbl_appconfig')->where('setting', 'paystack_config')->find_one();
    $paystack_config = json_decode($config['value'], true);
    
    $ui->assign('paystack_public_key', $paystack_config['public_key'] ?? '');
    $ui->assign('paystack_secret_key', $paystack_config['secret_key'] ?? '');
    $ui->assign('paystack_webhook_secret', $paystack_config['webhook_secret'] ?? '');
    $ui->assign('paystack_success_url', $paystack_config['success_url'] ?? U . 'order/view/');
    $ui->assign('paystack_failed_url', $paystack_config['failed_url'] ?? U . 'order/view/');
    
    $ui->display('paymentgateway/paystack.tpl');
}

/**
 * Saves the Paystack configuration
 */
function paystack_save_config()
{
    global $admin;
    $paystack_public_key = _post('paystack_public_key');
    $paystack_secret_key = _post('paystack_secret_key');
    $paystack_webhook_secret = _post('paystack_webhook_secret');
    $paystack_success_url = _post('paystack_success_url');
    $paystack_failed_url = _post('paystack_failed_url');

    if ($paystack_public_key != '' && $paystack_secret_key != '') {
        $d = ORM::for_table('tbl_appconfig')->where('setting', 'paystack_config')->find_one();
        if ($d) {
            $d->value = json_encode([
                'public_key' => $paystack_public_key,
                'secret_key' => $paystack_secret_key,
                'webhook_secret' => $paystack_webhook_secret,
                'success_url' => $paystack_success_url,
                'failed_url' => $paystack_failed_url
            ]);
            $d->save();
        } else {
            $d = ORM::for_table('tbl_appconfig')->create();
            $d->setting = 'paystack_config';
            $d->value = json_encode([
                'public_key' => $paystack_public_key,
                'secret_key' => $paystack_secret_key,
                'webhook_secret' => $paystack_webhook_secret,
                'success_url' => $paystack_success_url,
                'failed_url' => $paystack_failed_url
            ]);
            $d->save();
        }
        _log($admin['username'] . ' Updated Paystack Payment Gateway Configuration', 'Admin', $admin['id']);
        r2(U . 'paymentgateway/paystack', 's', 'Paystack Payment Gateway Configuration Saved Successfully');
    } else {
        r2(U . 'paymentgateway/paystack', 'e', 'Please enter Public Key and Secret Key');
    }
}

/**
 * Validates the Paystack configuration
 */
function paystack_validate_config()
{
    $config = ORM::for_table('tbl_appconfig')->where('setting', 'paystack_config')->find_one();
    if (!$config) {
        r2(U . 'paymentgateway', 'e', 'Paystack Payment Gateway is not configured yet');
    }
}

/**
 * Initiates a payment transaction with Paystack
 */
function paystack_create_transaction($trx, $user)
{
    $config = ORM::for_table('tbl_appconfig')->where('setting', 'paystack_config')->find_one();
    $paystack_config = json_decode($config['value'], true);
    
    $amount = intval($trx['price']) * 100; // Convert to kobo/cents
    $email = $user['email'];
    $reference = 'TRX-' . $trx['id'] . '-' . time();
    $callback_url = U . 'callback/paystack/' . $trx['id'];
    
    // Prepare the request data
    $data = [
        'email' => $email,
        'amount' => $amount,
        'reference' => $reference,
        'callback_url' => $callback_url,
        'metadata' => [
            'transaction_id' => $trx['id'],
            'username' => $user['username'],
            'plan_name' => $trx['plan_name']
        ]
    ];
    
    // Initialize transaction with Paystack API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.paystack.co/transaction/initialize');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $paystack_config['secret_key'],
        'Content-Type: application/json',
        'Cache-Control: no-cache'
    ]);
    
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($err) {
        r2(U . 'order/package', 'e', 'cURL Error: ' . $err);
    }
    
    $result = json_decode($response, true);
    
    if ($result && $result['status']) {
        // Update transaction record
        $d = ORM::for_table('tbl_payment_gateway')->find_one($trx['id']);
        $d->gateway_trx_id = $reference;
        $d->pg_url_payment = $result['data']['authorization_url'];
        $d->pg_request = $response;
        $d->save();
        
        // Redirect to payment page
        header('Location: ' . $result['data']['authorization_url']);
        exit;
    } else {
        r2(U . 'order/package', 'e', 'Failed to initialize payment: ' . ($result['message'] ?? 'Unknown error'));
    }
}

/**
 * Checks the status of a transaction
 */
function paystack_get_status($trx, $user)
{
    $config = ORM::for_table('tbl_appconfig')->where('setting', 'paystack_config')->find_one();
    $paystack_config = json_decode($config['value'], true);
    
    $reference = $trx['gateway_trx_id'];
    
    // Verify transaction with Paystack API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.paystack.co/transaction/verify/' . rawurlencode($reference));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $paystack_config['secret_key'],
        'Content-Type: application/json',
        'Cache-Control: no-cache'
    ]);
    
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($err) {
        r2(U . 'order/view/' . $trx['id'], 'e', 'cURL Error: ' . $err);
    }
    
    $result = json_decode($response, true);
    
    if ($result && $result['status'] && $result['data']['status'] === 'success') {
        // Payment successful, update transaction
        $d = ORM::for_table('tbl_payment_gateway')->find_one($trx['id']);
        $d->pg_paid_response = $response;
        $d->payment_method = $result['data']['channel'] ?? 'Paystack';
        $d->payment_channel = $result['data']['channel'] ?? 'Paystack';
        $d->paid_date = date('Y-m-d H:i:s');
        $d->status = 2; // Paid
        $d->save();
        
        // Create invoice
        $router_name = $trx['routers'];
        $plan_id = $trx['plan_id'];
        
        if (Package::rechargeUser($user['id'], $router_name, $plan_id, 'Paystack Payment', $trx['gateway'])) {
            r2(U . 'order/view/' . $trx['id'], 's', 'Payment successful');
        } else {
            r2(U . 'order/view/' . $trx['id'], 'e', 'Payment was successful but failed to recharge account');
        }
    } else {
        // Payment not successful yet
        r2(U . 'order/view/' . $trx['id'], 'w', 'Payment is still pending or failed. Status: ' . ($result['data']['status'] ?? 'unknown'));
    }
}

/**
 * Handles webhook notifications from Paystack
 */
function paystack_payment_notification()
{
    // Retrieve the request's body
    $input = file_get_contents('php://input');
    
    // Get Paystack signature header
    $signature = isset($_SERVER['HTTP_X_PAYSTACK_SIGNATURE']) ? $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] : '';
    
    // Get Paystack configuration
    $config = ORM::for_table('tbl_appconfig')->where('setting', 'paystack_config')->find_one();
    if (!$config) {
        header('HTTP/1.1 400 Bad Request');
        exit('Paystack not configured');
    }
    
    $paystack_config = json_decode($config['value'], true);
    $secret_key = $paystack_config['webhook_secret'] ?? $paystack_config['secret_key'];
    
    // Verify webhook signature
    if (!$signature || $signature !== hash_hmac('sha512', $input, $secret_key)) {
        // Invalid signature
        header('HTTP/1.1 401 Unauthorized');
        exit('Invalid signature');
    }
    
    // Parse webhook payload
    $event = json_decode($input, true);
    
    // Verify the event
    if (!$event || !isset($event['event']) || !isset($event['data']['reference'])) {
        header('HTTP/1.1 400 Bad Request');
        exit('Invalid webhook payload');
    }
    
    // Process only successful charge events
    if ($event['event'] === 'charge.success') {
        $reference = $event['data']['reference'];
        
        // Extract transaction ID from reference (format: TRX-{id}-{timestamp})
        $parts = explode('-', $reference);
        if (count($parts) >= 2 && $parts[0] === 'TRX') {
            $trx_id = $parts[1];
            
            // Find the transaction
            $trx = ORM::for_table('tbl_payment_gateway')->find_one($trx_id);
            if (!$trx) {
                header('HTTP/1.1 404 Not Found');
                exit('Transaction not found');
            }
            
            // Check if transaction is already paid
            if ($trx['status'] == 2) {
                // Already processed
                header('HTTP/1.1 200 OK');
                exit('Transaction already processed');
            }
            
            // Update transaction
            $trx->pg_paid_response = json_encode($event);
            $trx->payment_method = $event['data']['channel'] ?? 'Paystack';
            $trx->payment_channel = $event['data']['channel'] ?? 'Paystack';
            $trx->paid_date = date('Y-m-d H:i:s');
            $trx->status = 2; // Paid
            $trx->save();
            
            // Get user
            $user = ORM::for_table('tbl_customers')->where('username', $trx['username'])->find_one();
            if (!$user) {
                header('HTTP/1.1 404 Not Found');
                exit('User not found');
            }
            
            // Create invoice
            $router_name = $trx['routers'];
            $plan_id = $trx['plan_id'];
            
            Package::rechargeUser($user['id'], $router_name, $plan_id, 'Paystack Payment', $trx['gateway']);
            
            // Respond with success
            header('HTTP/1.1 200 OK');
            exit('Webhook processed successfully');
        }
    }
    
    // For other events, just acknowledge receipt
    header('HTTP/1.1 200 OK');
    exit('Event received');
}