<?php
$id = $modx->getOption('id',$scriptProperties,null);

if (!$id)
    $st = $modx->newObject('smProductPermissions');
else
    $st = $modx->getObject('smProductPermissions',$id);

if (!($st instanceof smProductPermissions))
    return $modx->error->failure($modx->lexicon('sm.error.invalidobject'));

$data = $scriptProperties;

$st->fromArray($data);

if ($st->save())
    return $modx->error->success();
return $modx->error->failure($modx->lexicon('sm.error.savefail'));

?>