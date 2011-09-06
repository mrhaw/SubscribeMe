<?php

/**
 * SubscribeMe class 
 */
class SubscribeMe {
    public $modx;
    public $config = array();
    private $chunks = array();
    public $periodUsable = array('D' => 'day', 'W' => 'week', 'M' => 'month', 'Y' => 'year');

    /**
     * @param \modX $modx
     * @param array $config
     * @return \SubscribeMe
     */
    function __construct(modX &$modx,array $config = array()) {
        $this->modx =& $modx;
 
        $basePath = $this->modx->getOption('subscribeme.core_path',$config,$this->modx->getOption('core_path').'components/subscribeme/');
        $assetsUrl = $this->modx->getOption('subscribeme.assets_url',$config,$this->modx->getOption('assets_url').'components/subscribeme/');
        $assetsPath = $this->modx->getOption('subscribeme.assets_path',$config,$this->modx->getOption('assets_path').'components/subscribeme/');
        $this->config = array_merge(array(
            'base_bath' => $basePath,
            'core_path' => $basePath,
            'model_path' => $basePath.'model/',
            'processors_path' => $basePath.'processors/',
            'elements_path' => $basePath.'elements/',
            'assets_path' => $assetsPath,
            'js_url' => $assetsUrl.'js/',
            'css_url' => $assetsUrl.'css/',
            'assets_url' => $assetsUrl,
            'connector_url' => $assetsUrl.'connector.php',
        ),$config);

        $this->modx->addPackage('subscribeme',$this->config['model_path']);
        $this->modx->lexicon->load('subscribeme:default');
    }

    /**
     * @param string $ctx Context name
     * @return bool
     */
    public function initialize($ctx = 'web') {
        return true;
    }

    /* getChunk & _GetTplChunk by splittingred */
    /**
    * Gets a Chunk and caches it; also falls back to file-based templates
    * for easier debugging.
    *
    * @access public
    * @param string $name The name of the Chunk
    * @param array $properties The properties for the Chunk
    * @return string The processed content of the Chunk
    */
    public function getChunk($name,$properties = array()) {
        $chunk = null;
        if (!isset($this->chunks[$name])) {
            $chunk = $this->_getTplChunk($name);
            if (empty($chunk)) {
                $chunk = $this->modx->getObject('modChunk',array('name' => $name),true);
                if ($chunk == false) return false;
            }
            $this->chunks[$name] = $chunk->getContent();
        } else {
            $o = $this->chunks[$name];
            $chunk = $this->modx->newObject('modChunk');
            $chunk->setContent($o);
        }
        $chunk->setCacheable(false);
        return $chunk->process($properties);
    }

    /**
    * Returns a modChunk object from a template file.
    *
    * @access private
    * @param string $name The name of the Chunk. Will parse to name.chunk.tpl
    * @param string $postFix The postfix to append to the name
    * @return modChunk/boolean Returns the modChunk object if found, otherwise
    * false.
    */
    private function _getTplChunk($name,$postFix = '.tpl') {
        $chunk = false;
        $f = $this->config['elements_path'].'chunks/'.$name.$postFix;
        if (file_exists($f)) {
            $o = file_get_contents($f);
            /* @var modChunk $chunk */
            $chunk = $this->modx->newObject('modChunk');
            $chunk->set('name',$name);
            $chunk->setContent($o);
        }
        return $chunk;
    }

    /**
     * @param \smTransaction $trans
     * @return bool|string Returns true if succesful, or an error string when something went wrong.
     */
    public function processTransaction(smTransaction $trans) {
        // Get the subscription
        $sub = $trans->getOne('Subscription');
        if (!($sub instanceof smSubscription))
            return 'Unable to find the subscription belonging to the transaction.';

        // Get the product
        $product = $sub->getOne('Product');
        if (!($product instanceof smProduct))
            return 'Unable to find the product belonging to the subscription.';

        // We'll need the period from the product
        $periodUsable = array('D' => 'day', 'W' => 'week', 'M' => 'month', 'Y' => 'year');
        $prodPeriod = $product->get('periods'); // the number of periods
        $prodPeriod .= ' '.$periodUsable[$product->get('period')]; // the actual period entity (day)

        // Recalculate the expires column.
        $subExpCur = $sub->get('expires');                  // Get the current expires time in format 2011-08-30 14:17:22
        $subExp = strtotime($subExpCur);                    // Parse the time into a unix timestamp
        if ($subExp < time()) $subExp = time();             // Make sure that the current expiry date is at least the same as now.
        $subExp = strtotime('+' . $prodPeriod,$subExp);     // Take the "+2 week" from the product, and add it.
        if ($subExp < $subExpCur)                           // Do a simple check to make sure the new expires date is larger than earlier
            return 'Error calculating the new expiring timestamp.';

        // Update the expires column
        $subExp = date('Y-m-d H:i:s',$subExp);          // First change it to a format MySQL will surely understand.
        $sub->set('expires', $subExp);                  // Change it
        if (!$sub->save())                              // Save & if that failed return an error.
            return 'Error updating subscription with new expires timestamp.';

        // Get the permissions to set from the product
        $pperms = $product->getMany('Permissions');

        foreach ($pperms as $pp) {
            // Only process if it's the right object type
            if ($pp instanceof smProductPermissions) {
                /* @var smProductPermissions $pp */
                $ppArray = $pp->toArray();
                // Check if the user is already a member of this group.
                $ugTest = $this->modx->getObject('modUserGroupMember',array(
                    'user_group' => $ppArray['usergroup'],
                    'role' => $ppArray['role'],
                    'member' => $sub->get('user_id')
                ));
                // If no user group with the requirements was found..
                if (!($ugTest instanceof modUserGroupMember)) {
                    // Create a new user group membership
                    /* @var modUserGroupMember $ug */
                    $ug = $this->modx->newObject('modUserGroupMember');
                    $ug->fromArray(
                        array(
                            'user_group' => $ppArray['usergroup'],
                            'role' => $ppArray['role'],
                            'member' => $sub->get('user_id')
                        )
                    );
                    if (!$ug->save())
                        return 'Error saving user group '.$ppArray['usergroup'].' with role '.$ppArray['role'];
                }
            }
        }

        // If we got here all things went as intended.
        $trans->set('completed',true);
        $trans->set('updatedon',date('Y-m-d H:i:s'));
        if ($trans->save()) {
            // We'll need to send an email to the user too, to confirm we received the payment.
            /* @var modUser $user */
            $user = $this->modx->getObject('modUser',$sub->get('user_id'));
            if ($user instanceof modUser) {
                $up = $user->getOne('Profile');
                $upa = array();
                if ($up instanceof modUserProfile)
                    $upa = $up->toArray();
                $chunk = $this->modx->getOption('subscribeme.email.confirmtransaction',null,'smConfirmTransactionEmail');
                $phs = array(
                    'user' => array_merge($user->toArray(),$upa),
                    'subscription' => $sub->toArray(),
                    'transaction' => $trans->toArray(),
                    'product' => $product->toArray(),
                    'settings' => $this->modx->config,
                );
                $msg = $this->getChunk($chunk,$phs);
                $subject =  $this->modx->getOption('subscribeme.email.confirmtransaction.subject',null,'Transaction processed for [[+product]] subscription');
                $subject = str_replace(
                    array('[[+transid]]','[[+product]]'),
                    array($trans->get('trans_id'),$product->get('name')),
                    $subject
                );
                if ($user->sendEmail($msg,array('subject' => $subject)) !== true)
                    return 'Error sending payment confirmation email.';
                return true;
            }
            else {
                return 'Error fetching user to send transaction confirmation email.';
            }
        }
        else
            return 'Error marking transaction as completed.';
    }


}
        
?>