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
 * @var array $scriptProperties
 */

$path = $modx->getOption('subscribeme.core_path',null,$modx->getOption('core_path').'components/subscribeme/').'classes/';
$modx->getService('sm','SubscribeMe',$path);
$modx->setLogLevel(modX::LOG_LEVEL_INFO);
$modx->setLogTarget('ECHO');

/* Amount of user accounts to check per run */
$limit = 100;
/* Time to keep the last verified users' ID in cache.
1 day means that 24hrs after the last user was validated, it will start at 0 again. */
$cacheTime = 60 * 60 * 24 * .5;

/* Get last validated user id */
$last = $modx->cacheManager->get('subscribeme/last_validated');
if ($last < 1) $last = 0;

$c = $modx->newQuery('modUser');
$c->sortby('id', 'ASC');
$c->where(array(
    'id:>' => $last,
));
$c->select(array('id','username'));

foreach ($modx->getIterator('modUser', $c) as $user) {
    $id = $user->get('id');
    echo 'Validating user: ' . $user->get('username') . '(#' . $id.")\n";
    $modx->sm->checkForExpiredSubscriptions($id);
    $modx->cacheManager->set('subscribeme/last_validated', $id, $cacheTime);
}

echo 'Done';
