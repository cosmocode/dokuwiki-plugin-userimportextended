<?php

use dokuwiki\Form\Form;

/**
 * Class helper_plugin_userimportextended
 */
class helper_plugin_userimportextended extends DokuWiki_Plugin {



    /**
     * @param array $importFailures
     */
    public function setImportFailures($importFailures)
    {
        $this->importFailures = $importFailures;
    }


}
