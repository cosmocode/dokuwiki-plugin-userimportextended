<?php

use dokuwiki\Form\Form;

/**
 * Admin plugin based on usermanager with additional features:
 * - importing passwords
 * - setting defaults for empty values
 */
class admin_plugin_userimportextended extends DokuWiki_Admin_Plugin
{
    const DEFAULT_EMPTY = ['groups'];

    /** @var auth_plugin_authplain */
    protected $_auth;
    /** @var array  */
    protected $_import_failures = [];
    /** @var array */
    protected $defaults = ['email', 'name', 'password', 'groups'];

    /**
     * Constructor
     */
    public function __construct()
    {
        /** @var auth_plugin_authplain $auth */
        global $auth;

        if (!$auth instanceof auth_plugin_authplain) {
            msg($this->getLang('error_badauth'));
            return;
        }

        $this->_auth = $auth;

        // attempt to retrieve any import failures from the session
        if (!empty($_SESSION['import_failures'])){
            $this->_import_failures = $_SESSION['import_failures'];
        }
    }

    /**
     * handle user request
     */
    public function handle()
    {
        global $INPUT;
        $cmd = $INPUT->param('cmd');
        if (!empty($cmd)) {
            switch(key($cmd)) {
                case "import":
                    if (!checkSecurityToken()) return false;
                    if (!$this->_auth->canDo('addUser')) return false;

                    if ($this->validateDefaults() === true) {
                        $this->_import();
                    }
                    break;
                case "importfails":
                    $this->_downloadImportFailures();
                    break;
            }
        }
        return true;
    }

    /**
     * Output html of the admin page
     */
    public function html()
    {
        print $this->locale_xhtml('intro');
        $this->printFormHTML();
        $this->printFailuresHTML();
    }

    /**
     * Prints the import form
     */
    protected function printFormHTML()
    {
        $form = new Form(['enctype' => 'multipart/form-data', 'id' => 'plugin__userimportextended_csv']);
        $form->setHiddenField('do', 'admin');
        $form->setHiddenField('page', $this->getPluginName());
        $form->addFieldsetOpen($this->getLang('legend_defaults'));
        $form->addTextInput('defaults[name]', $this->getLang('form_name') . '*');
        $form->addHTML('<br>');
        $form->addTextInput('defaults[email]', $this->getLang('form_email') . '*');
        $form->addHTML('<br>');
        $form->addTextInput('defaults[password]', $this->getLang('form_password') . '*');
        $form->addHTML('<br>');
        $form->addTextInput('defaults[groups]', $this->getLang('form_groups'));
        $form->addFieldsetClose();
        $form->addFieldsetOpen($this->getLang('legend_csv'));
        $form->addElement(new \dokuwiki\Form\InputElement('file', 'import'))->attr('accept', '.csv');
        $form->addHTML('<br>');
        $form->addButton('cmd[import]', $this->getLang('btn_import'));
        $form->addFieldsetClose();
        echo $form->toHTML();
    }

    /**
     * Prints a table of failed imports
     */
    protected function printFailuresHTML()
    {
        global $ID;
        $failure_download_link = wl($ID,array('do'=>'admin','page'=>'userimportextended','cmd[importfails]'=>1));

        if ($this->_import_failures) {
            $digits = strlen(count($this->_import_failures));
            ptln('<div class="level3 import_failures">');
            ptln('  <h3>'.$this->lang['import_header'].'</h3>');
            ptln('  <table class="import_failures">');
            ptln('    <thead>');
            ptln('      <tr>');
            ptln('        <th class="line">'.$this->lang['line'].'</th>');
            ptln('        <th class="error">'.$this->lang['error'].'</th>');
            ptln('        <th class="userid">'.$this->lang['user_id'].'</th>');
            ptln('        <th class="userpass">'.$this->lang['user_pass'].'</th>');
            ptln('        <th class="username">'.$this->lang['user_name'].'</th>');
            ptln('        <th class="usermail">'.$this->lang['user_mail'].'</th>');
            ptln('        <th class="usergroups">'.$this->lang['user_groups'].'</th>');
            ptln('      </tr>');
            ptln('    </thead>');
            ptln('    <tbody>');
            foreach ($this->_import_failures as $line => $failure) {
                ptln('      <tr>');
                ptln('        <td class="lineno"> '.sprintf('%0'.$digits.'d',$line).' </td>');
                ptln('        <td class="error">' .$failure['error'].' </td>');
                ptln('        <td class="field userid"> '.hsc($failure['user'][0]).' </td>');
                ptln('        <td class="field userpass"> '.hsc($failure['user'][1]).' </td>');
                ptln('        <td class="field username"> '.hsc($failure['user'][2]).' </td>');
                ptln('        <td class="field usermail"> '.hsc($failure['user'][3]).' </td>');
                ptln('        <td class="field usergroups"> '.hsc($failure['user'][4]).' </td>');
                ptln('      </tr>');
            }
            ptln('    </tbody>');
            ptln('  </table>');
            ptln('  <p><a href="'.$failure_download_link.'">'.$this->lang['import_downloadfailures'].'</a></p>');
            ptln('</div>');
        }
    }

    /**
     * Tries to set all defaults. Returns false if any of the required defaults are empty.
     *
     * @return bool
     */
    protected function validateDefaults()
    {
        foreach ($this->defaults as $field) {
            if (!in_array($field, self::DEFAULT_EMPTY) && empty($_REQUEST['defaults'][$field])) {
                msg($this->getLang('error_required_defaults'), -1);
                return false;
            }
            $this->defaults[$field] = $_REQUEST['defaults'][$field];

            // make sure groups include "user"
            if ($field === 'groups' && strpos($_REQUEST['defaults'][$field], 'user') === false) {
                $this->defaults[$field] .= ',user';
            }
        }
        return true;
    }

    /**
     * Import a file of users in csv format
     *
     * csv file should have 5 columns, user_id, password, full name, email, groups (comma separated)
     *
     * @return bool whether successful
     */
    protected function _import() {
        // check we are allowed to add users
        if (!checkSecurityToken()) return false;
        if (!$this->_auth->canDo('addUser')) return false;

        // check file uploaded ok.
        $upl = $this->_isUploadedFile($_FILES['import']['tmp_name']);
        if (empty($_FILES['import']['size']) || !empty($_FILES['import']['error']) && $upl) {
            msg($this->lang['import_error_upload'],-1);
            return false;
        }
        // retrieve users from the file
        $this->_import_failures = array();
        $import_success_count = 0;
        $import_fail_count = 0;
        $line = 0;
        $fd = fopen($_FILES['import']['tmp_name'],'r');
        if ($fd) {
            while($csv = fgets($fd)){
                if (!utf8_check($csv)) {
                    $csv = utf8_encode($csv);
                }
                $raw = str_getcsv($csv);
                $error = '';                        // clean out any errors from the previous line
                // data checks...
                if (1 == ++$line) {
                    if ($raw[0] == 'user_id' || $raw[0] == $this->lang['user_id']) continue;    // skip headers
                }
                // in contrast to User Manager, 5 columns are required
                if (count($raw) < 5) {                                        // need at least five fields
                    $import_fail_count++;
                    $error = sprintf($this->lang['import_error_fields'], count($raw));
                    $this->_import_failures[$line] = array('error' => $error, 'user' => $raw, 'orig' => $csv);
                    continue;
                }

                $clean = $this->_cleanImportUser($raw, $error);
                if ($clean && $this->_addImportUser($clean, $error)) {
                    $sent = $this->_notifyUser($clean[0],$clean[1],false);
                    if (!$sent){
                        msg(sprintf($this->lang['import_notify_fail'],$clean[0],$clean[3]),-1);
                    }
                    $import_success_count++;
                } else {
                    $import_fail_count++;
                    $this->_import_failures[$line] = array('error' => $error, 'user' => $raw, 'orig' => $csv);
                }
            }
            msg(sprintf($this->lang['import_success_count'], ($import_success_count+$import_fail_count), $import_success_count),($import_success_count ? 1 : -1));
            if ($import_fail_count) {
                msg(sprintf($this->lang['import_failure_count'], $import_fail_count),-1);
            }
        } else {
            msg($this->lang['import_error_readfail'],-1);
        }

        // save import failures into the session
        if (!headers_sent()) {
            session_start();
            $_SESSION['import_failures'] = $this->_import_failures;
            session_write_close();
        }
        return true;
    }

    /**
     * Replaces empty values with defaults
     *
     * @param array $candidate
     */
    protected function insertDefaults(&$candidate)
    {
        if (empty($candidate[1])) {
            $candidate[1] = $this->defaults['password'];
        }
        if (empty($candidate[2])) {
            $candidate[2] = $this->defaults['name'];
        }
        if (empty($candidate[3])) {
            $candidate[3] = $this->defaults['email'];
        }
        if (empty($candidate[4])) {
            $candidate[4] = $this->defaults['groups'];
        }
    }

    /**
     * Returns cleaned user data
     *
     * @param array $candidate raw values of line from input file
     * @param string $error
     * @return array|false cleaned data or false
     */
    protected function _cleanImportUser($candidate, &$error) {
        global $INPUT;

        // fill in defaults if needed
        $this->insertDefaults($candidate);

        // kludgy ....
        $INPUT->set('userid', $candidate[0]);
        $INPUT->set('userpass', $candidate[1]);
        $INPUT->set('username', $candidate[2]);
        $INPUT->set('usermail', $candidate[3]);
        $INPUT->set('usergroups', $candidate[4]);

        $cleaned = $this->_retrieveUser();
        list($user,/* $pass */,$name,$mail,/* $grps */) = $cleaned;
        if (empty($user)) {
            $error = $this->lang['import_error_baduserid'];
            return false;
        }

        // no need to check password, handled elsewhere

        if (!($this->_auth->canDo('modName') xor empty($name))){
            $error = $this->lang['import_error_badname'];
            return false;
        }

        if ($this->_auth->canDo('modMail')) {
            if (empty($mail) || !mail_isvalid($mail)) {
                $error = $this->lang['import_error_badmail'];
                return false;
            }
        } else {
            if (!empty($mail)) {
                $error = $this->lang['import_error_badmail'];
                return false;
            }
        }

        return $cleaned;
    }

    /**
     * Adds imported user to auth backend
     *
     * Required a check of canDo('addUser') before
     *
     * @param array  $user   data of user
     * @param string &$error reference catched error message
     * @return bool whether successful
     */
    protected function _addImportUser($user, & $error){
        if (!$this->_auth->triggerUserMod('create', $user)) {
            $error = $this->lang['import_error_create'];
            return false;
        }

        return true;
    }

    /**
     * Retrieve & clean user data from the form
     *
     * @param bool $clean whether the cleanUser method of the authentication backend is applied
     * @return array (user, password, full name, email, array(groups))
     */
    protected function _retrieveUser($clean=true) {
        /** @var DokuWiki_Auth_Plugin $auth */
        global $auth;
        global $INPUT;

        $user = [];
        $user[0] = ($clean) ? $auth->cleanUser($INPUT->str('userid')) : $INPUT->str('userid');
        $user[1] = $INPUT->str('userpass');
        $user[2] = $INPUT->str('username');
        $user[3] = $INPUT->str('usermail');
        $user[4] = explode(',',$INPUT->str('usergroups'));
        $user[5] = $INPUT->str('userpass2');                // repeated password for confirmation

        $user[4] = array_map('trim',$user[4]);
        if($clean) $user[4] = array_map(array($auth,'cleanGroup'),$user[4]);
        $user[4] = array_filter($user[4]);
        $user[4] = array_unique($user[4]);
        if(!count($user[4])) $user[4] = null;

        return $user;
    }

    /**
     * Send password change notification email
     *
     * @param string $user         id of user
     * @param string $password     plain text
     * @param bool   $status_alert whether status alert should be shown
     * @return bool whether succesful
     */
    protected function _notifyUser($user, $password, $status_alert=true) {
        $sent = auth_sendPassword($user,$password);
        if ($sent) {
            if ($status_alert) {
                msg($this->lang['notify_ok'], 1);
            }
        } else {
            if ($status_alert) {
                msg($this->lang['notify_fail'], -1);
            }
        }

        return $sent;
    }

    /**
     * Downloads failures as csv file
     */
    protected function _downloadImportFailures(){

        // ==============================================================================================
        // GENERATE OUTPUT
        // normal headers for downloading...
        header('Content-type: text/csv;charset=utf-8');
        header('Content-Disposition: attachment; filename="importfails.csv"');
#       // for debugging assistance, send as text plain to the browser
#       header('Content-type: text/plain;charset=utf-8');

        // output the csv
        $fd = fopen('php://output','w');
        foreach ($this->_import_failures as $fail) {
            fputs($fd, $fail['orig']);
        }
        fclose($fd);
        die;
    }

    /**
     * wrapper for is_uploaded_file to facilitate overriding by test suite
     *
     * @param string $file filename
     * @return bool
     */
    protected function _isUploadedFile($file) {
        return is_uploaded_file($file);
    }
}
