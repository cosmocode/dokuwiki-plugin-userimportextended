<?php
/**
 * English language file
 *
 * @author Chris Smith <chris@jalakai.co.uk>
 * @author Anna Dabrowska <dokuwiki@cosmocode.de>
 */

$lang['menu'] = 'Extended User Import';

$lang['btn_import'] = 'Import users';
$lang['legend_defaults'] = 'Defaults';
$lang['legend_csv'] = 'CSV file';
$lang['form_email'] = 'Email';
$lang['form_name'] = 'Full name';
$lang['form_password'] = 'Password';
$lang['form_groups'] = 'Groups (optional)';

$lang['error_badauth'] = 'Supported authentication method authplain is not enabled';
$lang['error_required_defaults'] = 'Please fill in the required default values.';

// import & errors (the same as in usermanager)
$lang['line']        = 'Line no.';
$lang['error']       = 'Error message';

$lang['user_id']     = 'User';
$lang['user_pass']   = 'Password';
$lang['user_name']   = 'Real Name';
$lang['user_mail']   = 'Email';
$lang['user_groups'] = 'Groups';

$lang['import_userlistcsv'] = 'User list file (CSV):  ';
$lang['import_header'] = 'Most Recent Import - Failures';
$lang['import_success_count'] = 'User Import: %d users found, %d imported successfully.';
$lang['import_failure_count'] = 'User Import: %d failed. Failures are listed below.';
$lang['import_error_fields']  = "Insufficient fields, found %d, require 5.";
$lang['import_error_baduserid'] = "User-id missing";
$lang['import_error_badname'] = 'Bad name';
$lang['import_error_badmail'] = 'Bad email address';
$lang['import_error_upload']  = 'Import Failed. The csv file could not be uploaded or is empty.';
$lang['import_error_readfail'] = 'Import Failed. Unable to read uploaded file.';
$lang['import_error_create']  = 'Unable to create the user';
$lang['import_notify_fail']   = 'Notification message could not be sent for imported user, %s with email %s.';
$lang['import_downloadfailures'] = 'Download Failures as CSV for correction';
