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

$start = $modx->getOption('start',$scriptProperties,0);
$limit = $modx->getOption('limit',$scriptProperties,20);
$sort = $modx->getOption('sort',$scriptProperties,'createdon');
$dir = $modx->getOption('dir',$scriptProperties,'desc');

$search = $modx->getOption('query',$scriptProperties,null);
$subscriber = $modx->getOption('subscriber',$scriptProperties,null);
$method = $modx->getOption('method',$scriptProperties,null);

$c = $modx->newQuery('smTransaction');

$c->innerJoin('modUser','User');
$c->innerJoin('modUserProfile','Profile','User.id = Profile.internalKey');

if ($search) {
    $c->where(
        array(
            'reference:LIKE' => "%$search%",
            'OR:method:LIKE' => "%$search%",
            'OR:User.username:LIKE' => "%$search%",
            'OR:Profile.fullname:LIKE' => "%$search%",
        )
    );
    if (is_numeric($search))
        $c->orCondition(array('trans_id' => $search));
}

if (is_numeric($subscriber)) {
    $c->where(array('user_id' => $subscriber));
}

if ($method) {
    $c->where(array('method' => $method));
}

$matches = $modx->getCount('smTransaction',$c);

$c->sortby($sort,$dir);
$c->sortby('createdon','desc');
$c->limit($limit,$start);

$c->select(array('smTransaction.*','User.username AS user_username','Profile.fullname AS user_name'));

$results = array();

$r = $modx->getCollection('smTransaction',$c);
$cs = $modx->getOption('subscribeme.currencysign',null,'$');
foreach ($r as $rs) {
    $ta = $rs->get(array('trans_id','sub_id','user_id','reference','method','amount','completed','createdon','updatedon','user_name','user_username'));
    $ta['updatedon'] = ($ta['updatedon'] == '0000-00-00 00:00:00') ? '' : date($modx->config['manager_date_format'].' '.$modx->config['manager_time_format'],strtotime($ta['updatedon']));
    $ta['createdon'] = ($ta['createdon'] == '0000-00-00 00:00:00') ? '' : date($modx->config['manager_date_format'].' '.$modx->config['manager_time_format'],strtotime($ta['createdon']));
    $ta['amount'] = $cs.$ta['amount'];
    $results[] = $ta;
}

if (count($results) == 0) {
    return $modx->error->failure($modx->lexicon('sm.error.noresults')); 
}
$ra = array(
    'success' => true,
    'total' => $matches,
    'results' => $results
);

return $modx->toJSON($ra);

?>