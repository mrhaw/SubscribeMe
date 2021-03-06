<?php
/**
 * SubscribeMe
 *
 * Copyright 2011 by Mark Hamstra <business@markhamstra.nl>
 *
 * This file is part of SubscribeMe, a subscriptions management extra for MODX Revolution
 *
 * SubscribeMe is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * SubscribeMe is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * SubscribeMe; if not, write to the Free Software Foundation, Inc., 59 Temple Place,
 * Suite 330, Boston, MA 02111-1307 USA
*/

/* @var string $path
 * @var modX $modx
 * @var fiHooks $hook
 * @var FormIt $formit
 */
require_once($path.'classes/paypal/paypal.class.php');

$debug = $modx->getOption('debug',$scriptProperties,$modx->getOption('subscribeme.debug',null,false));

/* We will need to be logged in */
if (!is_numeric($modx->user->id)) return $modx->sendUnauthorizedPage();

/* Make sure we have a token and accompanying subscription ID */
$token = $modx->getOption('token',$hook->getValues());
if (empty($token)) return 'Error, no token found.';

/* @var smPaypalToken $pt */
$pt = $modx->getObject('smPaypalToken',array('token' => $token));
if (!($pt instanceof smPaypalToken)) return 'Error, no transaction token found.';

/* @var smSubscription $subscription */
$subscription = $pt->getOne('Subscription');
if (!($subscription instanceof smSubscription)) return 'Error, no subscription found.';

/* Confirm valid transaction */
if ($subscription->get('user_id') != $modx->user->id) { return 'Please make sure you are logged in with the user that requested this subscription.'; }
//if ($subscription->get('active') === true) return 'Subscription already Active.';

/* Prepare order / transaction data */
$product = $subscription->getOne('Product');
$prod    = $product->toArray();
$sub     = $subscription->toArray();

/* If we passed the initial checks, let's move forward. */
/* Prepare PayPal settings */
$p = array();
$p['currency_code'] = $modx->getOption('subscribeme.currencycode',null,'USD');
$p['amount'] = $prod['price'];
$p['return_url'] = $modx->makeUrl($hook->formit->config['completedResource'], '', '', 'full');
$p['fail_id'] = $hook->formit->config['errorResource'];

/* Check if we're in the sandbox or live and fetch the appropriate credentials */
$p['sandbox'] = $modx->getOption('subscribeme.paypal.sandbox',null,true);
if (!$p['sandbox']) {
    /* We're live */
    $paypal = new phpPayPal(false);
    $p['username'] = $modx->getOption('subscribeme.paypal.api_username');
    $p['password'] = $modx->getOption('subscribeme.paypal.api_password');
    $p['signature'] = $modx->getOption('subscribeme.paypal.api_signature');
} else {
    /* We're using the sandbox */
    $paypal = new phpPayPal(true);
    $p['username'] = $modx->getOption('subscribeme.paypal.sandbox_username');
    $p['password'] = $modx->getOption('subscribeme.paypal.sandbox_password');
    $p['signature'] = $modx->getOption('subscribeme.paypal.sandbox_signature');
}

$paypal->API_USERNAME = $p['username'];
$paypal->API_PASSWORD = $p['password'];
$paypal->API_SIGNATURE = $p['signature'];

$paypal->ip_address = $_SERVER['REMOTE_ADDR'];

if ($debug) var_dump(array('PayPal Settings' => $p,  'Transaction' => $trans, 'Product' => $prod,'Subscription' => $sub));

/* Start filling in some data */
$paypal->version = '57.0';
$paypal->token = $token;

/* Set recurring payment information */
$start_time = strtotime(date('m/d/Y'));
$start_date = date('Y-m-d\T00:00:00\Z',$start_time);
$paypal->profile_start_date = urlencode($start_date);

$paypal->invoice_number = $trans['id'];
$paypal->currency = $p['currency_code'];
$paypal->billing_start = date('d-m-Y\TH:i:s\Z');// '2011-09-05T05:00:00.0000000Z'; //$start_date;//'2011-09-04 T01:09:14Z%20'; //date('Y-m-d\T+H:i:s\Z ',strtotime('+1month'));
$paypal->billing_period = ucfirst($modx->sm->periodUsable[$prod['period']]);
$paypal->billing_frequency = $prod['periods'];
$paypal->billing_amount = $prod['price'];
$paypal->billing_type = 'RecurringPayments';
$paypal->billing_type2 = 'RecurringPayments';
$paypal->billing_agreement = urlencode($prod['name'].' (#'.$sub['sub_id'].')');
$paypal->description = urlencode($prod['name'].' (#'.$sub['sub_id'].')');
$paypal->profile_reference = $sub['sub_id'];
$paypal->payer_id = $_REQUEST['PayerID'];
$paypal->tax_amount = $prod['amount_vat'];
$paypal->ship_amount = $prod['amount_shipping'];
$paypal->subscriber_name = urlencode($user['fullname']);

/* Create the profile */
$paypal->create_recurring_payments_profile();

$response = $paypal->Response;
$success = false;

if (isset($response['PROFILESTATUS'])) {
    switch (strtolower($response['PROFILESTATUS'])) {
        case 'activeprofile':
            $success = true;
            /* We succesfully set up the recurring payments profile! PARTY!!!
             * Do note that that doesn't mean we got money yet - we'll need to wait for the IPN message for that.
             * We will, however, set the PROFILEID to the subscription so we can identify it and use that to fetch info.
             */
            $subscription->set('pp_profileid',$response['PROFILEID']);
            $subscription->set('active',true);
            if ($subscription->save())
                return $modx->sendRedirect($p['return_url']);
            break;

        case 'pendingprofile':
            $success = true;
            $subscription->set('pp_profileid',$response['PROFILEID']);
            if ($subscription->save())
                return $modx->sendRedirect($p['return_url'].'&pending=true');
            break;

        default: 
            return 'Unknown PayPal response.';
            break;
    }
}
else {
    // Uh oh.. trouble!
    if ($debug) var_dump($response);
    $modx->log(1,print_r($response,true));
    $modx->sendRedirect($modx->makeUrl($p['fail_id'],'',array('errorcode' => 'PPRPE', 'errormsg' => 'An error occured setting up your recurring profile.')));
    return 'An error occured setting up the recurring payments. ';
}
?>