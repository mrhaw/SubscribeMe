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

/* @var modX $modx
 * @var fiHooks $hook
 * @var FormIt $formit
 */
if (!is_numeric($modx->user->id)) { return $modx->sendUnauthorizedPage(); }

$path = $modx->getOption('subscribeme.core_path',null,$modx->getOption('core_path').'components/subscribeme/').'classes/';
$modx->getService('sm','SubscribeMe',$path);

$prod = $hook->getValue('product');

$product = $modx->getObject('smProduct',$prod);
if (!($product instanceof smProduct)) {
  $modx->log(modX::LOG_LEVEL_ERROR,'There is no product with ID '.$prod);
  return 'There is no product with ID '.$prod;
}

/* @var smSubscription $sub */
$sub = $modx->newObject('smSubscription');
$sub->fromArray(array(
  'user_id' => $modx->user->id,
  'product_id' => $prod,
  'active' => false
));
if ($sub->save()) {
  $subid = $sub->get('sub_id');
  $url = $modx->makeUrl($formit->config['optionsResource'], '', array('subid' => $subid));
  return $modx->sendRedirect($url);
}
else {
    $modx->log(modX::LOG_LEVEL_ERROR,'Error saving subscription.');
    return false;
}

?>
