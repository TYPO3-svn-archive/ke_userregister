<?php

/* * *************************************************************
 *  Copyright notice
 *
 *  (c) 2009-2012 Andreas Kiefer <kiefer@kennziffer.com>
 *  All rights reserved
 *
 * 	Fields to select day, month and year of birth:
 *  (c) 2010 Ingvar Harjaks <ingvar@somecodehere.com>
 *
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 * ************************************************************* */

require_once(PATH_tslib . 'class.tslib_pibase.php');
require_once(PATH_t3lib . 'class.t3lib_basicfilefunc.php');
require_once(t3lib_extMgm::extPath('ke_userregister', 'lib/class.tx_keuserregister_lib.php'));
define('ADMIN_HASH_PREFIX', 'admin');

// require new mail api if t3 v > 4.5
if (t3lib_utility_VersionNumber::convertVersionNumberToInteger(TYPO3_version) >= 4005000)
	require_once(PATH_t3lib . 'mail/class.t3lib_mail_message.php');
else
	require_once(PATH_t3lib . 'class.t3lib_htmlmail.php');

/**
 * Plugin 'Register Form' for the 'ke_userregister' extension.
 *
 * @author	Andreas Kiefer <kiefer@kennziffer.com>
 * @package	TYPO3
 * @subpackage	tx_keuserregister
 */
class tx_keuserregister_pi1 extends tslib_pibase {

	var $prefixId = 'tx_keuserregister_pi1';  // Same as class name
	var $scriptRelPath = 'pi1/class.tx_keuserregister_pi1.php'; // Path to this script relative to the extension dir.
	var $extKey = 'ke_userregister'; // The extension key.
	var $confirmationType = '';

	/**
	 * The main method of the PlugIn
	 *
	 * @param	string		$content: The PlugIn content
	 * @param	array		$conf: The PlugIn configuration
	 * @return	The content that is displayed on the website
	 */
	function main($content, $conf) {
		$this->conf = $conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();

		// Configuring so caching is not expected. This value means that 
		// no cHash params are ever set. We do this, because it's a USER_INT object!
		$this->pi_USER_INT_obj = 1; 

		// get tooltip class if extension is installed
		if (t3lib_extMgm::isLoaded('fe_tooltip')) {
			require_once(t3lib_extMgm::extPath('fe_tooltip') . 'class.tx_fetooltip.php');
			$this->tooltipAvailable = true;
		} else {
			$this->tooltipAvailable = false;
		}

		// init lib
		$this->lib = t3lib_div::makeInstance('tx_keuserregister_lib');

		// get general extension setup
		$this->conf = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_keuserregister.'];

		// folder for uploads
		$this->fileUploadDir = $this->conf['upload.']['path'] ? $this->conf['upload.']['path'] : 'uploads/tx_keuserregister/';

		// GET FLEXFORM DATA
		$this->pi_initPIflexForm();
		$piFlexForm = $this->cObj->data['pi_flexform'];
		if (is_array($piFlexForm['data'])) {
			foreach ($piFlexForm['data'] as $sheet => $data) {
				foreach ($data as $lang => $value) {
					foreach ($value as $key => $val) {
						$this->ffdata[$key] = $this->pi_getFFvalue($piFlexForm, $key, $sheet);
					}
				}
			}
		}

		// plugin mode: ts value overwrites ff value
		if (isset($this->conf['mode']))
			$this->mode = $this->conf['mode'];
		else {
			switch ($this->ffdata['mode']) {
				case 0: $this->mode = 'create';
					break;
				case 1: $this->mode = 'edit';
					break;
			}
		}

		// get fields from configuration
		$this->fields = $this->conf[$this->mode . '.']['fields.'];

		// get fields that are evaluated as email and trim its value
		foreach ($this->fields as $name => $conf) {
			$fieldName = substr($name, 0, -1);
			if (strstr($conf['eval'], 'email') && !empty($this->piVars[$fieldName])) {
				$this->piVars[$fieldName] = trim($this->piVars[$fieldName]);
			}
		}

		// overwrite username config if email is used as username
		if ($this->conf['emailIsUsername'])
			unset($this->fields['username.']);

		// overwrite password field if edit mode is set
		if ($this->mode == 'edit')
			unset($this->fields['password.']);

		// get html template
		$this->templateCode = $this->cObj->fileResource($this->conf['templateFile']);

		// include css
		$cssFile = $GLOBALS['TSFE']->tmpl->getFileName($this->conf['cssFile']);
		if (!empty($cssFile)) {
			if (t3lib_div::compat_version('6.0'))
				$GLOBALS['TSFE']->getPageRenderer()->addCssFile($cssFile);
			else
				$GLOBALS['TSFE']->additionalHeaderData[$this->prefixId . '_css'] = '<link rel="stylesheet" type="text/css" href="' . $cssFile . '" />';
		}

		// check if it is a user confirmation (double opt-in confirmation),
		// or an admin confirmation
		if (t3lib_div::_GET('confirm') || t3lib_div::_GET('decline')) {
			$hashUserInput = t3lib_div::_GET('confirm') ? t3lib_div::_GET('confirm') : t3lib_div::_GET('decline');
			$this->confirmationType = (substr($hashUserInput, 0, strlen(ADMIN_HASH_PREFIX)) == ADMIN_HASH_PREFIX) ? 'admin' : 'user';
		}

		// process incoming registration confirmation
		if (t3lib_div::_GET('confirm'))
			$content = $this->processConfirm();
		// process incoming registration decline
		else if (t3lib_div::_GET('decline'))
			$content = $this->processDecline();
		// process incoming email change confirmation
		else if (t3lib_div::_GET('mailconfirm'))
			$content = $this->processEmailChangeConfirm();
		// process incoming email change decline
		else if (t3lib_div::_GET('maildecline'))
			$content = $this->processEmailChangeDecline();
		// show registration / edit form
		else
			$content = $this->piVars['step'] == 'evaluate' ? $this->evaluateFormData() : $this->renderForm();

		return $this->pi_wrapInBaseClass($content);
	}

	/**
	 * Process the double opt-in confirmation
	 *
	 * @return string
	 */
	function processConfirm() {
		// check if hash duration is set
		if (!$this->conf['hashDays'])
			die($this->prefixId . ': ERROR: hash duration is not set');

		// generate timestamp for checking hash age
		$tstampCalculated = time() - ($this->conf['hashDays'] * (60 * 60 * 24));

		// select from hash table
		$fields = '*';
		$table = 'tx_keuserregister_hash';
		$hashCompare = $this->lib->removeXSS(t3lib_div::_GET('confirm'));
		$where = 'hash="' . $hashCompare . '" ';
		$where .= 'and tstamp>' . $tstampCalculated . '  ';
		$hashRes = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields, $table, $where);
		$anz = $GLOBALS['TYPO3_DB']->sql_num_rows($hashRes);
		// if number of records not eq 1: ERROR!
		if ($anz != 1) {
			// print error message
			$content = $this->cObj->getSubpart($this->templateCode, '###SUB_MESSAGE###');
			$markerArray = array(
			    'headline' => $this->pi_getLL('confirmation_error_headline'),
			    'message' => sprintf($this->pi_getLL('confirmation_error_message'), t3lib_div::getIndpEnv('TYPO3_SITE_URL')),
			);
			$content = $this->cObj->substituteMarkerArray($content, $markerArray, $wrap = '###|###', $uppercase = 1);
			return $content;
		}
		// if number of found records is eq 1: activate user record
		else {
			// get hash row
			$hashRow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($hashRes);

			// update feuser record
			$table = 'fe_users';
			$where = 'uid="' . intval($hashRow['feuser_uid']) . '" ';
			$fields_values = array('tstamp' => time());

			// activate the user now, if it is an confirmation from the admin
			// or if the admin confirmation function is disabled
			if ($this->confirmationType == 'admin' || !$this->conf['adminConfirmationEnabled']) {
				$fields_values['disable'] = 0;
			}

			if (!$GLOBALS['TYPO3_DB']->exec_UPDATEquery($table, $where, $fields_values)) {
				die ($this->prefixId . ': ERROR: DATABASE ERROR');
			}

			// delete hash in database
			$this->deleteHashEntry($hashRow['hash']);

			// print success message
			$content = $this->cObj->getSubpart($this->templateCode, '###SUB_MESSAGE###');
			$markerArray = array(
			    'headline' => $this->pi_getLL('confirmation_success_headline')
			);

			// the success message depends on wether the admin confirmation function
			// is enabled or not
			if ($this->conf['adminConfirmationEnabled']) {
				if ($this->confirmationType == 'admin') {
					$markerArray['message'] = sprintf($this->pi_getLL('confirmation_success_message_adminconfirmation_laststep'), t3lib_div::getIndpEnv('TYPO3_SITE_URL'));
				} else {
					$markerArray['message'] = sprintf($this->pi_getLL('confirmation_success_message_adminconfirmation'), t3lib_div::getIndpEnv('TYPO3_SITE_URL'));
				}
			} else {
			    $markerArray['message'] = sprintf($this->pi_getLL('confirmation_success_message'), t3lib_div::getIndpEnv('TYPO3_SITE_URL'));
			}

			// send success email to the user
			if ($this->conf['successMailAfterConfirmation'] && (!$this->conf['adminConfirmationEnabled'] || $this->confirmationType == 'admin')) {
				$this->sendUserMail($hashRow['feuser_uid'], 'confirm');
			}

			// send email to admin after successful registration
			if ($this->conf['adminMailAfterConfirmation'] && ($this->confirmationType == 'user')) {
				$subject = $this->pi_getLL('admin_mail_subject_confirmation_success');
				$textAbove = $this->pi_getLL('admin_mail_text_confirmation_success');

				// add links for confirmation or denial
				if ($this->conf['adminConfirmationEnabled']) {
					$hash = $this->generateHash($hashRow['feuser_uid'], ADMIN_HASH_PREFIX);
					$textBelow = $this->renderAdminMailLinks($hashRow['feuser_uid'], $hash);
				} else {
					$textBelow = '';
				}

				$this->sendAdminMail($hashRow['feuser_uid'], $subject, $textAbove, $textBelow);
			}

			// auto-login new user?
			if ($this->conf['autoLoginAfterConfirmation'] && !$this->conf['adminConfirmationEnabled']) {
				// get userdata and process auto-login
				$userRecord = $this->getUserRecord($hashRow['feuser_uid']);
				if ($this->autoLogin($userRecord['username'], $userRecord['password'])) {
					// login succesful?
					$markerArray['message'] = $this->pi_getLL('confirmation_success_message_autologin');
				}
			}

			$content = $this->cObj->substituteMarkerArray($content, $markerArray, $wrap = '###|###', $uppercase = 1);

			// show backlink?
			if ($this->conf['backlink.']['generate']) {

				// get backlink params from hash table
				$backlinkParamsArray = t3lib_div::xml2array($hashRow['backlinkparams']);

				// process backlink params
				$backlinkParamsString = '';
				if (is_array($backlinkParamsArray)) {
					foreach ($backlinkParamsArray as $param => $value) {
						if (is_array($value)) {
							foreach ($value as $index => $val) {
								$backlinkParamsString .= '&' . $param . '[' . $index . ']=' . $val;
							}
						}
					}
				}

				// generate backlink
				$linkconf['parameter'] = intval($hashRow['backlinkpid']);
				$linkconf['additionalParams'] = $backlinkParamsString;
				$backlink = $this->cObj->typoLink($this->pi_getLL('backlink_text'), $linkconf);

				// add backlink to content
				$content .= $this->cObj->getSubpart($this->templateCode, '###SUB_CONFIRMATION_SUCCESS_BACKLINK###');
				$content = $this->cObj->substituteMarker($content, '###BACKLINK###', $backlink);
			}


			return $content;
		}
	}


	/**
	 * generate hash and save in database
	 * 
	 * @param integer $feuser_uid 
	 * @param string $prefix
	 * @param string $fields_values Additional fields to store in the hash table (for backlink generation)
	 * @return string
	 */
	function generateHash($feuser_uid, $prefix = '', $fields_values = array()) {
		$hash = $prefix . $this->getUniqueCode();
		$table = 'tx_keuserregister_hash';

		$fields_values['hash'] = $hash;
		$fields_values['feuser_uid'] = intval($feuser_uid);
		$fields_values['tstamp'] = time();

		if ($GLOBALS['TYPO3_DB']->exec_INSERTquery($table, $fields_values)) {
			return $hash;
		} else {
			die ($this->prefixId . ': ERROR: DATABASE ERROR');
		}
	}


	/**
	 * renders the links used in the admin mail for confirmation or denial
	 * of a registration
	 * 
	 * @param integer fe_user uid
	 * @param string hash 
	 * @return string
	 */
	function renderAdminMailLinks($feuser_uid, $hash) {
		$content = $this->cObj->getSubpart($this->templateCode, '###ADMIN_MAIL_SUB_CONFIRMATION_NEEDED###');

		// generate confirmation link
		$linkconf['parameter'] = $GLOBALS['TSFE']->id;
		$linkconf['additionalParams'] = '&confirm=' . $hash;
		$confirmLinkUrl = t3lib_div::locationHeaderUrl($this->cObj->typoLink_URL($linkconf));
		$confirmationLink = '<a href="' . $confirmLinkUrl . '">' . $confirmLinkUrl . '</a>';

		// generate decline link
		unset($linkconf);
		$linkconf['parameter'] = $GLOBALS['TSFE']->id;
		$linkconf['additionalParams'] = '&decline=' . $hash;
		$declineLinkUrl = t3lib_div::locationHeaderUrl($this->cObj->typoLink_URL($linkconf));
		$declineLink = '<a href="' . $declineLinkUrl . '">' . $declineLinkUrl . '</a>';

		$markerArray = array(
		    'admin_mail_text_confirmuser' => $this->pi_getLL('admin_mail_text_confirmuser'),
		    'admin_mail_text_declineuser' => $this->pi_getLL('admin_mail_text_declineuser'),
		    'admin_confirm_link' => $confirmationLink,
		    'admin_decline_link' => $declineLink
		);

		return $this->cObj->substituteMarkerArray($content, $markerArray, '###|###', 1);

	}

	/*
	 * Sends the mail to the user when a registration has been
	 * confirmed or declined.
	 * possible values for $action: "confirm" or "decline"
	 * 
	 * @param $userUid int
	 * @param $action text
	 * @return void
	 */
	function sendUserMail($userUid, $action = 'confirm') {
		// send mail to user, ignore enable fields in "decline" mode, since
		// the user is not enabled yet
		$userData = $this->getUserRecord($userUid, ($action == 'decline'));

		if (is_array($userData)) {
			// use salutation based on users gender
			$salutationCode = $userData['gender'] == 1 ? 'female' : 'male';

			$htmlBody = $this->cObj->getSubpart($this->templateCode, '###CONFIRMATION_MAIL###');
			$mailMarkerArray = array(
			    'salutation' => $this->pi_getLL('salutation_' . $salutationCode),
			    'first_name' => $userData['first_name'],
			    'last_name' => $userData['last_name'],
			    'farewell_text' => $this->pi_getLL('farewell_text'),
			    'site_url' => t3lib_div::getIndpEnv('TYPO3_SITE_URL'),
			);

			switch ($action) {
				case 'decline':
					$mailMarkerArray['text'] = $this->pi_getLL('confirmation_decline_text');
					$subject = $this->pi_getLL('confirmation_success_subject');
					break;

				case 'confirm':
				default:
					$mailMarkerArray['text'] = $this->pi_getLL('confirmation_success_text');
					$subject = $this->pi_getLL('confirmation_success_subject');
					break;
			}

			$htmlBody = $this->cObj->substituteMarkerArray($htmlBody, $mailMarkerArray, '###|###', 1);
			$this->sendNotificationEmail($userData['email'], $subject, $htmlBody);
		}
	}

	/**
	 * Process the decline of a registration
	 *
	 * @return void
	 */
	function processDecline() {

		// check if hash duration is set
		if (!$this->conf['hashDays'])
			die($this->prefixId . ': ERROR: no hash duration is not set');

		// generate timestamp for checking hash age
		$tstampCalculated = time() - ($this->conf['hashDays'] * (60 * 60 * 24));

		// select from hash table
		$fields = '*';
		$table = 'tx_keuserregister_hash';
		$hashCompare = $this->lib->removeXSS(t3lib_div::_GET('decline'));
		$where = 'hash="' . $hashCompare . '" ';
		$where .= 'and tstamp>' . $tstampCalculated . '  ';
		$hashRes = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields, $table, $where);
		$anz = $GLOBALS['TYPO3_DB']->sql_num_rows($hashRes);
		// if number of records not eq 1: ERROR!
		if ($anz != 1) {
			// print error message
			$content = $this->cObj->getSubpart($this->templateCode, '###SUB_MESSAGE###');
			$markerArray = array(
			    'headline' => $this->pi_getLL('decline_error_headline'),
			    'message' => $this->pi_getLL('decline_error_message'),
			);
			$content = $this->cObj->substituteMarkerArray($content, $markerArray, $wrap = '###|###', $uppercase = 1);
			return $content;
		}
		// if number of found records is eq 1: completely delete user record
		else {
			// get hash row
			$hashRow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($hashRes);

			// send mail to user if an admin declined the confirmation
			
			if ($this->confirmationType == 'admin') {
				$this->sendUserMail($hashRow['feuser_uid'], 'decline');
			}

			// delete frontend user
			$table = 'fe_users';
			$where = 'uid=' . intval($hashRow['feuser_uid']);
			if (!$GLOBALS['TYPO3_DB']->exec_DELETEquery($table, $where)) {
				die($this->prefixId . ': ERROR: DATABASE ERROR');
			}

			// delete hash after processing
			$this->deleteHashEntry($hashRow['hash']);

			// print success message
			$contentTemplate = $this->cObj->getSubpart($this->templateCode, '###SUB_MESSAGE###');

			if ($this->confirmationType == 'admin') {
				$message = $this->pi_getLL('decline_success_message_admin');
			} else {
				$message = $this->pi_getLL('decline_success_message');
			}

			$markerArray = array(
			    'headline' => $this->pi_getLL('decline_success_headline'),
			    'message' => $message
			);

			$content = $this->cObj->substituteMarkerArray($contentTemplate, $markerArray, '###|###', 1);

			return $content;
		}
	}

	/**
	 * Process the email change request
	 *
	 * @return string
	 */
	function processEmailChangeConfirm() {
		// check if hash duration is set
		if (!$this->conf['hashDays'])
			die($this->prefixId . ': ERROR: hash duration is not set');

		// generate timestamp for checking hash age
		$tstampCalculated = time() - ($this->conf['hashDays'] * (60 * 60 * 24));

		// select from hash table
		$fields = '*';
		$table = 'tx_keuserregister_hash';
		$hashCompare = $this->lib->removeXSS(t3lib_div::_GET('mailconfirm'));
		$where = 'hash="' . $hashCompare . '" ';
		$where .= 'and tstamp>' . $tstampCalculated . '  ';
		$hashRes = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields, $table, $where);
		$anz = $GLOBALS['TYPO3_DB']->sql_num_rows($hashRes);
		// if number of records not eq 1: ERROR!
		if ($anz != 1) {
			// print error message
			$content = $this->cObj->getSubpart($this->templateCode, '###SUB_MESSAGE###');
			$markerArray = array(
			    'headline' => $this->pi_getLL('mail_confirmation_error_headline'),
			    'message' => sprintf($this->pi_getLL('mail_confirmation_error_message'), t3lib_div::getIndpEnv('TYPO3_SITE_URL')),
			);
			$content = $this->cObj->substituteMarkerArray($content, $markerArray, $wrap = '###|###', $uppercase = 1);
			return $content;
		}
		// if number of found records is eq 1: activate user record
		else {
			$hashRow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($hashRes);

			// update fe user record with new email address
			$table = 'fe_users';
			$where = 'uid="' . intval($hashRow['feuser_uid']) . '" ';
			$fields_values['tstamp'] = time();
			$fields_values['email'] = $this->lib->removeXSS($hashRow['new_email']);

			// set username too if email is used as username
			if ($this->conf['emailIsUsername'])
				$fields_values['username'] = $this->lib->removeXSS($hashRow['new_email']);

			// delete hash after processing
			if ($GLOBALS['TYPO3_DB']->exec_UPDATEquery($table, $where, $fields_values, $no_quote_fields = FALSE)) {
				$this->deleteHashEntry($hashRow['hash']);
			}
			// print success message
			$content = $this->cObj->getSubpart($this->templateCode, '###SUB_MESSAGE###');
			$markerArray = array(
			    'headline' => $this->pi_getLL('mail_confirmation_success_headline'),
			    'message' => sprintf($this->pi_getLL('mail_confirmation_success_message'), t3lib_div::getIndpEnv('TYPO3_SITE_URL')),
			);
			$content = $this->cObj->substituteMarkerArray($content, $markerArray, $wrap = '###|###', $uppercase = 1);
			return $content;
		}
	}

	/**
	 * Process the email change decline
	 *
	 * @return string
	 */
	function processEmailChangeDecline() {

		// check if hash duration is set
		if (!$this->conf['hashDays'])
			die($this->prefixId . ': ERROR: no hash duration is not set');

		// generate timestamp for checking hash age
		$tstampCalculated = time() - ($this->conf['hashDays'] * (60 * 60 * 24));

		// select from hash table
		$fields = '*';
		$table = 'tx_keuserregister_hash';
		$hashCompare = $this->lib->removeXSS(t3lib_div::_GET('maildecline'));
		$where = 'hash="' . $hashCompare . '" ';
		$where .= 'and tstamp>' . $tstampCalculated . '  ';
		$hashRes = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields, $table, $where);
		$anz = $GLOBALS['TYPO3_DB']->sql_num_rows($hashRes);
		// if number of records not eq 1: ERROR!
		if ($anz != 1) {
			// print error message
			$content = $this->cObj->getSubpart($this->templateCode, '###SUB_MESSAGE###');
			$markerArray = array(
			    'headline' => $this->pi_getLL('mail_decline_error_headline'),
			    'message' => $this->pi_getLL('mail_decline_error_message'),
			);
			$content = $this->cObj->substituteMarkerArray($content, $markerArray, $wrap = '###|###', $uppercase = 1);
			return $content;
		}
		// if number of found records is eq 1: completely delete user record
		else {
			// delete hash after processing
			$this->deleteHashEntry(t3lib_div::_GET('maildecline'));
			// print success message
			$content = $this->cObj->getSubpart($this->templateCode, '###SUB_MESSAGE###');
			$markerArray = array(
			    'headline' => $this->pi_getLL('mail_decline_success_headline'),
			    'message' => $this->pi_getLL('mail_decline_success_message'),
			);
			$content = $this->cObj->substituteMarkerArray($content, $markerArray, $wrap = '###|###', $uppercase = 1);
			return $content;
		}
	}

	/**
	 * Delete a hasch entry from the database
	 *
	 * @param string
	 * @return void
	 */
	function deleteHashEntry($hash) {
		$table = 'tx_keuserregister_hash';
		$hashCompare = $this->lib->removeXSS($hash);
		$where = 'hash="' . $hashCompare . '" ';
		$GLOBALS['TYPO3_DB']->exec_DELETEquery($table, $where);
	}

	/**
	 * Renders the registration form
	 *
	 * @param array
	 * @return string
	 */
	function renderForm($errors = array()) {

		// initial checks
		// edit profile and no login
		if ($this->mode == 'edit' && !$GLOBALS['TSFE']->loginUser) {
			$content = $this->cObj->getSubpart($this->templateCode, '###SUB_MESSAGE###');
			$content = $this->cObj->substituteMarker($content, '###HEADLINE###', $this->pi_getLL('no_login_headline'));
			$content = $this->cObj->substituteMarker($content, '###MESSAGE###', sprintf($this->pi_getLL('no_login_message'), $GLOBALS['TSFE']->fe_user->user['username']));
			return $content;
		}

		// user already logged in
		else if ($this->mode != 'edit' && $GLOBALS['TSFE']->loginUser) {
			$content = $this->cObj->getSubpart($this->templateCode, '###SUB_MESSAGE###');
			$content = $this->cObj->substituteMarker($content, '###HEADLINE###', $this->pi_getLL('already_logged_in_headline'));
			$content = $this->cObj->substituteMarker($content, '###MESSAGE###', sprintf($this->pi_getLL('already_logged_in_message'), $GLOBALS['TSFE']->fe_user->user['username']));
			return $content;
		}

		// get general markers
		$this->markerArray = $this->getGeneralMarkers();

		// generate backlink?
		if ($this->conf['backlink.']['generate']) {
			$backlinkHiddenContent = '';
			// set backlink pid as hidden field
			if ($this->piVars['backlinkPid'])
				$backlinkHiddenContent = '<input type="hidden" name="' . $this->prefixId . '[backlinkPid]" value="' . intval($this->piVars['backlinkPid']) . '" />';

			// get backlink params values
			$backlinkParams = $this->getBacklinkParamsArray();

			// generate params as hidden fields
			if (sizeof($backlinkParams)) {
				foreach ($backlinkParams as $param => $value) {
					if (is_array($value)) {
						foreach ($value as $index => $val) {
							$name = $param . '[' . $index . ']';
							$backlinkHiddenContent .= '<input type="hidden" name="' . $name . '" value="' . $this->lib->removeXSS($val) . '" />';
						}
					}
					else
						$backlinkHiddenContent .= '<input type="hidden" name="' . $param . '" value="' . $this->lib->removeXSS($value) . '" />';
				}
			}
			// fill marker
			$this->markerArray['backlink_hidden'] = $backlinkHiddenContent;
		} else {
			// fill marker with empty content if backlink generation deactivated
			$this->markerArray['backlink_hidden'] = '';
		}

		// get data from db when editing profile
		if ($this->mode == 'edit') {
			$fields = '*';
			$table = 'fe_users';
			$where = 'uid="' . intval($GLOBALS['TSFE']->fe_user->user['uid']) . '" ';
			$where .= $this->cObj->enableFields($table);
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields, $table, $where, '', '', '1');
			$anz = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
			$userRow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);

			// set db value as piVar value when not already sent by the form
			foreach ($this->fields as $fieldName => $fieldConf) {
				$fieldName = str_replace('.', '', $fieldName);

				// special handling for checkboxes
				// process empty post value
				if ($fieldConf['type'] == 'checkbox') {
					// form not sent yet
					if (!isset($this->piVars['step']))
						$this->piVars[$fieldName] = $userRow[$fieldName];
				}

				// direct mail
				else if ($fieldConf['type'] == 'directmail') {
					if (!isset($this->piVars[$fieldName])) {
						// get directmail values from db
						$this->dmailValues = array();
						$fields = 'uid_local,uid_foreign';
						$table = 'sys_dmail_feuser_category_mm';
						$where = 'uid_local="' . intval($GLOBALS['TSFE']->fe_user->user['uid']) . '" ';
						$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields, $table, $where);
						$anz = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
						while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
							$this->dmailValues[] = $row['uid_foreign'];
						}
					}
					// form already sent - use pivars
					else {
						if (is_array($this->piVars[$fieldName])) {
							foreach ($this->piVars[$fieldName] as $cat => $value) {
								$this->dmailValues[] = $cat;
							}
						}
					}
				} else if (!isset($this->piVars[$fieldName])) {
					$this->piVars[$fieldName] = $userRow[$fieldName];

					// special handling for date fields
					// check for german date format which is DD.MM.YYYY
					if (strstr($fieldConf['eval'], 'date-de')) {
						$this->piVars[$fieldName] = date('d.m.Y', $this->piVars[$fieldName]);
					}

					// check for us date format which is MM/DD/YYYY
					if (strstr($fieldConf['eval'], 'date-us')) {
						$this->piVars[$fieldName] = date('m/d/Y', $this->piVars[$fieldName]);
					}

					// Hook for own prefill values
					// You will have to define one class per prefill Value
					// example:
					// $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tx_keuserregister']['prefillValue_gender'][] = 'EXT:ke_userregisterhooks/class.user_keuserregisterhooks.php:&user_keuserregisterhooks_prefillValueGender';
					if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tx_keuserregister']['prefillValue_' . $fieldName])) {
						foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tx_keuserregister']['prefillValue_' . $fieldName] as $_classRef) {
							$_procObj = & t3lib_div::getUserObj($_classRef);
							$this->piVars[$fieldName] = $_procObj->generatePrefillValue($this->piVars[$fieldName], $this);
						}
					}
				}
			}
		}

		foreach ($this->fields as $fieldName => $fieldConf) {
			$fieldName = str_replace('.', '', $fieldName);

			$this->markerArray['label_' . $fieldName] = $this->pi_getLL('label_' . $fieldName);
			$this->markerArray['value_' . $fieldName] = $this->piVars[$fieldName];

			// mark field as required
			if (strstr($fieldConf['eval'], 'required'))
				$this->markerArray['label_' . $fieldName] .= $this->cObj->getSubpart($this->templateCode, '###SUB_REQUIRED###');

			// render input field
			$this->markerArray['input_' . $fieldName] = $this->renderInputField($fieldConf, $fieldName);

			// wrap input field if error occured
			if ($errors[$fieldName]) {
				$this->markerArray['input_' . $fieldName] =
					$this->cObj->getSubpart($this->templateCode, '###SUB_ERRORWRAP_BEGIN###')
					. $this->markerArray['input_' . $fieldName]
					. $this->cObj->getSubpart($this->templateCode, '###SUB_ERRORWRAP_END###');
			}

			// mark field when errors occured
			if ($errors[$fieldName]) {

				// fill summarized error message for "date of birth"  fields
				if ($fieldName == 'dayofbirth' || $fieldName == 'monthofbirth' || $fieldName == 'yearofbirth') {
					$this->markerArray['error_dateofbirth'] = $errors[$fieldName];
				}

				// fill other error markers
				$this->markerArray['error_' . $fieldName] = $errors[$fieldName];
			}
			else
				$this->markerArray['error_' . $fieldName] = '';
		}

		// clear "date of birth" error marker if no error occured
		if (!$errors['dayofbirth'] && !$errors['monthofbirth'] && !$errors['yearofbirth']) {
			$this->markerArray['error_dateofbirth'] = '';
		}

		// Hook for additional form markers
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tx_keuserregister']['additionalMarkers'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tx_keuserregister']['additionalMarkers'] as $_classRef) {
				$_procObj = & t3lib_div::getUserObj($_classRef);
				$_procObj->additionalMarkers($this->markerArray, $this, $errors);
			}
		}


		// get subpart
		if ($this->mode == 'edit')
			$content = $this->cObj->getSubpart($this->templateCode, '###EDIT_FORM###');
		else
			$content = $this->cObj->getSubpart($this->templateCode, '###REGISTRATION_FORM###');

		// generate salutation for edit form
		// use salutation based on users gender
		if ($this->mode == 'edit' && $this->piVars['first_name'] != "" && $this->piVars['last_name'] != "" && $this->piVars['gender'] != "") {
			$salutationCode = $this->piVars['gender'] == 1 ? 'female' : 'male';
			$this->markerArray['salutation'] = $this->pi_getLL('salutation_' . $salutationCode);
			$this->markerArray['edit_welcome_text'] = $this->pi_getLL('edit_welcome_text');
			$this->markerArray['first_name'] = $this->piVars['first_name'];
			$this->markerArray['last_name'] = $this->piVars['last_name'];
		} else {
			$content = $this->cObj->substituteSubpart($content, '###SUB_SALUTATION###', '', $recursive = 1);
		}

		// add birthday field label
		$this->markerArray['label_birthday'] = $this->pi_getLL('label_birthday');

		// substitute marker array
		$content = $this->cObj->substituteMarkerArray($content, $this->markerArray, $wrap = '###|###', $uppercase = 1);

		// hide username field if email is used as username
		if ($this->conf['emailIsUsername'])
			$content = $this->cObj->substituteSubpart($content, '###SUB_FIELD_USERNAME###', '');

		// hide password field if edit mode is set
		if ($this->mode == 'edit')
			$content = $this->cObj->substituteSubpart($content, '###SUB_FIELD_PASSWORD###', '');

		return $content;
	}

	/*
	 * function getBacklinkParamsArray
	 *
	 * @return array
	 */

	function getBacklinkParamsArray() {
		$backlinkParams = t3lib_div::trimExplode(',', $this->conf['backlink.']['parameters'], true);
		if (sizeof($backlinkParams)) {
			foreach ($backlinkParams as $param) {
				// check if param is an array element
				if (strpos($param, '[')) {
					// param is array element
					$posBrace = strpos($param, '[');
					$extPart = substr($param, 0, $posBrace);
					$paramPart = substr($param, $posBrace + 1, -1);
					$extGet = t3lib_div::_GP($extPart);
					$value = $extGet[$paramPart];
					$backlinkHiddenArray[$extPart][$paramPart] = $extGet[$paramPart];
				} else {
					// "normal" get value
					$backlinkHiddenArray[$extPart][$paramPart] = t3lib_div::_GP($param);
				}
			}
			return $backlinkHiddenArray;
		}
	}

	/**
	 * Renders a tooltip for one form field
	 *
	 * @param string Text to display in the tooltip
	 * @return string html code for tooltip
	 */
	function renderTooltip($tooltipText) {
		if ($tooltipText && $this->tooltipAvailable) {
			return tx_fetooltip::tooltip('help.gif', $tooltipText);
		} else {
			return '';
		}
	}

	/**
	 * renders an input field
	 *
	 * @param array field configuration
	 * @param string field name
	 * @return string
	 */
	function renderInputField($fieldConf, $fieldName) {
		switch ($fieldConf['type']) {

			case 'text':
				$content = $this->cObj->getSubpart($this->templateCode, '###SUB_INPUT_TEXT###');
				$tempMarkerArray = array(
				    'name' => $this->prefixId . '[' . $fieldName . ']',
				    'value' => $this->piVars[$fieldName],
				    'tooltip' => $this->renderTooltip($fieldConf['tooltip'])
				);
				$content = $this->cObj->substituteMarkerArray($content, $tempMarkerArray, $wrap = '###|###', $uppercase = 1);
				break;

			case 'textarea':
				$content = $this->cObj->getSubpart($this->templateCode, '###SUB_INPUT_TEXTAREA###');
				$tempMarkerArray = array(
				    'name' => $this->prefixId . '[' . $fieldName . ']',
				    'value' => $this->piVars[$fieldName],
				    'tooltip' => $this->renderTooltip($fieldConf['tooltip'])
				);
				$content = $this->cObj->substituteMarkerArray($content, $tempMarkerArray, $wrap = '###|###', $uppercase = 1);
				break;

			case 'password':
				$value = $this->piVars['password'] ? $this->piVars['password'] : '';
				$valueAgain = $this->piVars['password_again'] ? $this->piVars['password_again'] : '';
				$content = $this->cObj->getSubpart($this->templateCode, '###SUB_PASSWORD###');
				$content = $this->cObj->substituteMarker($content, '###VALUE###', $value);
				$content = $this->cObj->substituteMarker($content, '###VALUE_AGAIN###', $valueAgain);
				$content = $this->cObj->substituteMarker($content, '###LABEL_PASSWORD_AGAIN###', $this->pi_getLL('label_password_again'));
				$content = $this->cObj->substituteMarker($content, '###TOOLTIP###', $this->renderTooltip($fieldConf['tooltip']));
				break;

			case 'checkbox':

				$fieldValues = explode(',', $fieldConf['values']);
				$numberOfValues = count($fieldValues);
				foreach ($fieldValues as $key => $value) {

					$checked = false;
					// set default value if create mode and form not sent
					if ($this->mode == 'create' && empty($this->piVars['step'])) {
						if ($value == $fieldConf['default'])
							$checked = true;
					}
					else {
						if ($numberOfValues > 1) {
							// multiple
							if (($this->piVars[$fieldName] >> $value - 1) & 1)
								$checked = true;
							else
								$checked = false;
						} else {
							// single
							$checked = $this->piVars[$fieldName] == $value ? true : false;
						}
					}

					$tempMarkerArray = array(
					    'name' => count($fieldValues) > 1 ? $this->prefixId . '[' . $fieldName . '][]' : $this->prefixId . '[' . $fieldName . ']',
					    'value' => $value,
					    'label' => $this->pi_getLL('label_' . $fieldName . '_' . $value),
					    #'checked' => ($this->piVars[$fieldName] == $value) ? 'checked="checked" ' : '',
					    'checked' => $checked ? 'checked="checked" ' : '',
					    'tooltip' => $this->renderTooltip($fieldConf['tooltip'])
					);

					$tempContent = $this->cObj->getSubpart($this->templateCode, '###SUB_CHECKBOX_ROW###');
					$tempContent = $this->cObj->substituteMarkerArray($tempContent, $tempMarkerArray, $wrap = '###|###', $uppercase = 1);
					$content .= $tempContent;
				}
				break;

			case 'radio':
				$fieldValues = explode(',', $fieldConf['values']);
				foreach ($fieldValues as $key => $value) {
					$tempMarkerArray = array(
					    'name' => $this->prefixId . '[' . $fieldName . ']',
					    'value' => $value,
					    'label' => $this->pi_getLL('label_' . $fieldName . '_' . $value),
					    'checked' => ($this->piVars[$fieldName] == $value) ? 'checked="checked" ' : '',
					    'tooltip' => $this->renderTooltip($fieldConf['tooltip'])
					);

					$tempContent = $this->cObj->getSubpart($this->templateCode, '###SUB_RADIO_ROW###');
					$tempContent = $this->cObj->substituteMarkerArray($tempContent, $tempMarkerArray, $wrap = '###|###', $uppercase = 1);
					$content .= $tempContent;
				}

				break;

			case 'select':
				$fieldValues = explode(',', $fieldConf['values']);
				foreach ($fieldValues as $key => $value) {
					$tempMarkerArray = array(
					    'value' => $value,
					    'label' => $this->pi_getLL('label_' . $fieldName . '_' . $value),
					    'selected' => ($this->piVars[$fieldName] == $value) ? 'selected="selected" ' : '',
					    'tooltip' => $this->renderTooltip($fieldConf['tooltip'])
					);
					$tempContent = $this->cObj->getSubpart($this->templateCode, '###SUB_SELECT_OPTION###');
					$tempContent = $this->cObj->substituteMarkerArray($tempContent, $tempMarkerArray, $wrap = '###|###', $uppercase = 1);
					$optionsContent .= $tempContent;
				}
				$content = $this->cObj->getSubpart($this->templateCode, '###SUB_SELECT###');
				$content = $this->cObj->substituteMarker($content, '###NAME###', $this->prefixId . '[' . $fieldName . ']');
				$content = $this->cObj->substituteSubpart($content, '###SUB_SELECT_OPTION###', $optionsContent);
				break;


			case 'directmail':
				$fields = '*';
				$table = 'sys_dmail_category';
				$where = 'sys_language_uid="' . $GLOBALS['TSFE']->sys_language_uid . '" ';
				if (!empty($fieldConf['values']))
					$where .= 'AND pid in("' . $this->lib->removeXSS($fieldConf['values']) . '") ';
				$where .= $this->cObj->enableFields($table);
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields, $table, $where);
				while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
					if (is_array($this->dmailValues)) {
						$checked = in_array($row['uid'], $this->dmailValues);
					}
					else
						$checked = false;

					$tempMarkerArray = array(
					    'name' => $this->prefixId . '[' . $fieldName . '][' . $row['uid'] . ']',
					    'value' => 1,
					    'label' => $row['category'],
					    'checked' => $checked ? 'checked="checked" ' : '',
					    'tooltip' => $this->renderTooltip($fieldConf['tooltip'])
					);
					$tempContent = $this->cObj->getSubpart($this->templateCode, '###SUB_CHECKBOX_ROW###');
					$tempContent = $this->cObj->substituteMarkerArray($tempContent, $tempMarkerArray, $wrap = '###|###', $uppercase = 1);
					$content .= $tempContent;
				}
				break;

			case 'select_db_relation':
				// compile sql query for select values
				$fields = '*';
				$table = $fieldConf['table'];
				$where = '1=1';
				if ($fieldConf['pid'])
					$where .= ' AND pid=' . intval($fieldConf['pid']);
				$where .= $this->cObj->enableFields($table);
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields, $table, $where, '', $fieldConf['displayField']);

				// build options
				// options from ts setup
				$fieldValues = explode(',', $fieldConf['values']);
				foreach ($fieldValues as $key => $value) {
					$tempMarkerArray = array(
					    'value' => $value,
					    'label' => $this->pi_getLL('label_' . $fieldName . '_' . $value),
					    'selected' => ($this->piVars[$fieldName] == $value) ? 'selected="selected" ' : '',
					    'tooltip' => $this->renderTooltip($fieldConf['tooltip'])
					);
					$tempContent = $this->cObj->getSubpart($this->templateCode, '###SUB_SELECT_OPTION###');
					$tempContent = $this->cObj->substituteMarkerArray($tempContent, $tempMarkerArray, $wrap = '###|###', $uppercase = 1);
					$optionsContent .= $tempContent;
				}

				// options from db result
				$optionsContent = '';
				while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
					$optionsContent .= '<option value="' . $row['uid'] . '" ';
					if ($this->piVars[$fieldName] == $row['uid'])
						$optionsContent .= ' selected="selected" ';
					$optionsContent .= '>' . $row[$fieldConf['displayField']] . '</option>';
				}

				// compile
				$content = $this->cObj->getSubpart($this->templateCode, '###SUB_SELECT###');
				$content = $this->cObj->substituteMarker($content, '###NAME###', $this->prefixId . '[' . $fieldName . ']');
				$content = $this->cObj->substituteSubpart($content, '###SUB_SELECT_OPTION###', $optionsContent);
				$content = $this->cObj->substituteMarker($content, '###TOOLTIP###', $this->renderTooltip($fieldConf['tooltip']));
				break;

			case 'image':
				// file already uploaded
				if ($this->piVars[$fieldName] != "") {

					// generate thumbnail
					$imageConf['file.'] = $fieldConf['file.'];
					$imageConf['file'] = $this->fileUploadDir . $this->piVars[$fieldName];
					$imageConf['altText'] = $this->piVars[$fieldName];
					$thumbnail = $this->cObj->IMAGE($imageConf);

					$content = $this->cObj->getSubpart($this->templateCode, '###SUB_INPUT_IMAGE_UPLOADED###');
					$tempMarkerArray = array(
					    'thumbnail' => $thumbnail,
					    'filename' => $this->piVars[$fieldName],
					    'fieldname' => $this->prefixId . '[' . $fieldName . ']',
					    'name_upload_new' => $this->prefixId . '[' . $fieldName . '_new]',
					    'tooltip' => $this->renderTooltip($fieldConf['tooltip'])
					);
					$content = $this->cObj->substituteMarkerArray($content, $tempMarkerArray, $wrap = '###|###', $uppercase = 1);
				}
				// no upload done
				else {
					$content = $this->cObj->getSubpart($this->templateCode, '###SUB_INPUT_IMAGE###');
					$tempMarkerArray = array(
					    'name' => $this->prefixId . '[' . $fieldName . ']',
					    //'value' => $this->piVars[$fieldName],
					    'tooltip' => $this->renderTooltip($fieldConf['tooltip'])
					);
					$content = $this->cObj->substituteMarkerArray($content, $tempMarkerArray, $wrap = '###|###', $uppercase = 1);
				}
				break;

			case 'country':

				// check if static tables are loaded
				// loaded
				if (t3lib_extMgm::isLoaded('static_info_tables')) {

					// check if current language extension is loaded, otherwise use english version
					$currentLang = $GLOBALS['TSFE']->tmpl->setup['config.']['language'];
					$staticInfoTableExtName = 'static_info_tables_' . $currentLang;
					$countryNameField = t3lib_extMgm::isLoaded($staticInfoTableExtName) ? 'cn_short_' . $currentLang : 'cn_short_en';

					// prefill value
					// not selected yet?
					if ($this->piVars[$fieldName] == "") {
						$fields = '*';
						$table = 'static_countries';
						$where = 'cn_iso_2="' . $currentLang . '" ';
						$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields, $table, $where, '', '', '1');
						$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
						$prefillValue = $row[$countryNameField];
					}
					else
						$prefillValue = $this->piVars[$fieldName];

					// get db data
					$fields = '*';
					$table = 'static_countries';
					$where = '';
					$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields, $table, $where, '', $countryNameField);

					// build options
					$optionsContent = '';
					while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
						$optionsContent .= '<option value="' . $row[$countryNameField] . '" ';
						if ($prefillValue == $row[$countryNameField])
							$optionsContent .= ' selected="selected" ';
						$optionsContent .= '>' . $row[$countryNameField] . '</option>';
					}
					$content = $this->cObj->getSubpart($this->templateCode, '###SUB_SELECT###');
					$content = $this->cObj->substituteMarker($content, '###NAME###', $this->prefixId . '[' . $fieldName . ']');
					$content = $this->cObj->substituteSubpart($content, '###SUB_SELECT_OPTION###', $optionsContent);
					$content = $this->cObj->substituteMarker($content, '###TOOLTIP###', $this->renderTooltip($fieldConf['tooltip']));
				}
				// not loaded
				else {
					$content = 'static_info_tables not loaded';
				}
				break;


			// Day of birth dropdown field
			case 'dayofbirth':
				// set default option
				$tempContent = $this->cObj->getSubpart($this->templateCode, '###SUB_SELECT_OPTION###');
				$tempMarkerArray = array(
				    'value' => '',
				    'label' => $this->pi_getLL('day'),
				    'selected' => ($this->piVars[$fieldName] == $value) ? 'selected="selected" ' : '',
				    'tooltip' => $this->renderTooltip($fieldConf['tooltip']),
				);
				$tempContent = $this->cObj->substituteMarkerArray($tempContent, $tempMarkerArray, $wrap = '###|###', $uppercase = 1);
				$optionsContent .= $tempContent;

				// loop options
				$low = (isset($fieldConf['low']) ? (int) $fieldConf['low'] : 1);
				$high = (isset($fieldConf['high']) ? (int) $fieldConf['high'] : 31);
				foreach (range(1, 31) as $key => $value) {
					$tempMarkerArray = array(
					    'value' => $value,
					    'label' => $value,
					    'selected' => ($this->piVars[$fieldName] == $value) ? 'selected="selected" ' : '',
					    'tooltip' => $this->renderTooltip($fieldConf['tooltip']),
					);
					$tempContent = $this->cObj->getSubpart($this->templateCode, '###SUB_SELECT_OPTION###');
					$tempContent = $this->cObj->substituteMarkerArray($tempContent, $tempMarkerArray, $wrap = '###|###', $uppercase = 1);
					$optionsContent .= $tempContent;
				}
				$content = $this->cObj->getSubpart($this->templateCode, '###SUB_SELECT###');
				$content = $this->cObj->substituteMarker($content, '###NAME###', $this->prefixId . '[' . $fieldName . ']');
				$content = $this->cObj->substituteSubpart($content, '###SUB_SELECT_OPTION###', $optionsContent);
				break;


			// month of birth dropdown field
			case 'monthofbirth':

				// set default option
				$tempContent = $this->cObj->getSubpart($this->templateCode, '###SUB_SELECT_OPTION###');
				$tempMarkerArray = array(
				    'value' => '',
				    'label' => $this->pi_getLL('month'),
				    'selected' => ($this->piVars[$fieldName] == $value) ? 'selected="selected" ' : '',
				    'tooltip' => $this->renderTooltip($fieldConf['tooltip']),
				);
				$tempContent = $this->cObj->substituteMarkerArray($tempContent, $tempMarkerArray, $wrap = '###|###', $uppercase = 1);
				$optionsContent .= $tempContent;

				// loop options
				foreach (range(1, 12) as $key => $value) {
					$label = strftime("%B", mktime(0, 0, 0, $value + 1, 0, 0));
					$tempMarkerArray = array(
					    'value' => $value,
					    'label' => $label,
					    'selected' => ($this->piVars[$fieldName] == $value) ? 'selected="selected" ' : '',
					    'tooltip' => $this->renderTooltip($fieldConf['tooltip']),
					);
					$tempContent = $this->cObj->getSubpart($this->templateCode, '###SUB_SELECT_OPTION###');
					$tempContent = $this->cObj->substituteMarkerArray($tempContent, $tempMarkerArray, $wrap = '###|###', $uppercase = 1);
					$optionsContent .= $tempContent;
				}
				$content = $this->cObj->getSubpart($this->templateCode, '###SUB_SELECT###');
				$content = $this->cObj->substituteMarker($content, '###NAME###', $this->prefixId . '[' . $fieldName . ']');
				$content = $this->cObj->substituteSubpart($content, '###SUB_SELECT_OPTION###', $optionsContent);
				break;


			// Year of birth dropdown field
			case 'yearofbirth':

				// set default option
				$tempContent = $this->cObj->getSubpart($this->templateCode, '###SUB_SELECT_OPTION###');
				$tempMarkerArray = array(
				    'value' => '',
				    'label' => $this->pi_getLL('year'),
				    'selected' => ($this->piVars[$fieldName] == $value) ? 'selected="selected" ' : '',
				    'tooltip' => $this->renderTooltip($fieldConf['tooltip']),
				);
				$tempContent = $this->cObj->substituteMarkerArray($tempContent, $tempMarkerArray, $wrap = '###|###', $uppercase = 1);
				$optionsContent .= $tempContent;

				$low = (isset($fieldConf['low']) ? (int) $fieldConf['low'] : 1970);
				$high = (isset($fieldConf['high']) ? (int) $fieldConf['high'] : strftime('%Y'));
				foreach (range($low, $high) as $key => $value) {
					$tempMarkerArray = array(
					    'value' => $value,
					    'label' => $value,
					    'selected' => ($this->piVars[$fieldName] == $value) ? 'selected="selected" ' : '',
					    'tooltip' => $this->renderTooltip($fieldConf['tooltip']),
					);
					$tempContent = $this->cObj->getSubpart($this->templateCode, '###SUB_SELECT_OPTION###');
					$tempContent = $this->cObj->substituteMarkerArray($tempContent, $tempMarkerArray, $wrap = '###|###', $uppercase = 1);
					$optionsContent .= $tempContent;
				}
				$content = $this->cObj->getSubpart($this->templateCode, '###SUB_SELECT###');
				$content = $this->cObj->substituteMarker($content, '###NAME###', $this->prefixId . '[' . $fieldName . ']');
				$content = $this->cObj->substituteSubpart($content, '###SUB_SELECT_OPTION###', $optionsContent);
				break;
		}
		return $content;
	}

	/**
	 * Get general markers as array
	 *
	 * @return array general markers
	 */
	function getGeneralMarkers() {

		// generate form action
		$linkconf['parameter'] = $GLOBALS['TSFE']->id;
		$formAction = $this->cObj->typoLink_URL($linkconf) . '#formstart';

		$generalMarkers = array(
		    'clearer' => $this->cObj->getSubpart($this->templateCode, '###SUB_CLEARER###'),
		    'form_name' => 'ke_userregister_registration_form',
		    'form_action' => $formAction,
		);

		return $generalMarkers;
	}

	/**
	 * check for german date format which is DD.MM.YYYY
	 *
	 * @param string $date_string
	 * @return bool
	 */
	function is_german_date($date_string = '') {
		$result = true;
		if ($date_string) {
			$date_array = t3lib_div::trimExplode('.', $date_string);
			$day = intval($date_array[0]);
			$month = intval($date_array[1]);
			$year = intval($date_array[2]);
			$result = checkdate($month, $day, $year);
		}
		return $result;
	}

	/**
	 * check for us date format which is DD.MM.YYYY
	 *
	 * @param string $date_string
	 * @return bool
	 */
	function is_us_date($date_string = '') {
		$result = true;
		if ($date_string) {
			$date_array = t3lib_div::trimExplode('/', $date_string);
			$day = intval($date_array[1]);
			$month = intval($date_array[0]);
			$year = intval($date_array[2]);
			$result = checkdate($month, $day, $year);
		}
		return $result;
	}

	/**
	 * process form field evaluations
	 *
	 * @return string
	 */
	function evaluateFormData() {

		$errors = array();

		foreach ($this->fields as $fieldName => $fieldConf) {

			$fieldName = str_replace('.', '', $fieldName);

			// check if required field is empty
			if (strstr($fieldConf['eval'], 'required') && empty($this->piVars[$fieldName])) {
				$errors[$fieldName] = $this->pi_getLL('error_required');
			}

			// check if field value is numeric
			if (strstr($fieldConf['eval'], 'numeric') && !is_numeric($this->piVars[$fieldName])) {
				$errors[$fieldName] = $this->pi_getLL('error_numeric');
			}

			// check if field value is email
			if (strstr($fieldConf['eval'], 'email') && !t3lib_div::validEmail($this->piVars[$fieldName])) {
				$errors[$fieldName] = $this->pi_getLL('error_valid_email');
			}

			// check if field value is int
			if (strstr($fieldConf['eval'], 'integer') && !$this->is_unsigned_int($this->piVars[$fieldName])) {
				$errors[$fieldName] = $this->pi_getLL('error_integer');
			}

			// check for german date format which is DD.MM.YYYY
			if (strstr($fieldConf['eval'], 'date-de') && !$this->is_german_date($this->piVars[$fieldName])) {
				$errors[$fieldName] = $this->pi_getLL('error_date');
			}

			// check for us date format which is MM/DD/YYYY
			if (strstr($fieldConf['eval'], 'date-us') && !$this->is_us_date($this->piVars[$fieldName])) {
				$errors[$fieldName] = $this->pi_getLL('error_date');
			}

			// checks for already existent username if username differs from the current username
			// (email is not used as username)
			if (!$this->conf['emailIsUsername'] && $fieldName == 'username') {
				if (!empty($this->piVars[$fieldName]) && ($this->piVars[$fieldName] != $GLOBALS['TSFE']->fe_user->user['username'])) {
					$where = 'username="' . $this->lib->removeXSS($this->piVars[$fieldName]) . '" AND deleted != 1';
					$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid', 'fe_users', $where);
					$anz = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
					if ($anz) {
						if ($this->conf['emailIsUsername'])
							$errors['email'] = $this->pi_getLL('error_email_existent');
						else
							$errors['username'] = $this->pi_getLL('error_username_existent');
					}
				}
			}

			// checks for already existent email
			// (email is used as username)
			if ($this->conf['emailIsUsername'] && $fieldName == 'email') {
				// check only if create user or user edited email value
				if ($this->mode == 'create' || ($this->mode == 'edit' && $this->emailHasChanged())) {
					if (!empty($this->piVars[$fieldName])) {
						$where = 'username="' . $this->lib->removeXSS($this->piVars[$fieldName]) . '" AND deleted != 1';
						$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid', 'fe_users', $where);
						$anz = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
						if ($anz) {
							if ($this->conf['emailIsUsername'])
								$errors['email'] = $this->pi_getLL('error_email_existent');
							else
								$errors['username'] = $this->pi_getLL('error_username_existent');
						}
					}
				}
			}

			// special evalutation for password field
			if ($fieldName == 'password') {

				// password and password again filled and equal values?
				if (empty($this->piVars['password']) || empty($this->piVars['password_again']) || ($this->piVars['password'] != $this->piVars['password_again'])) {
					$errors[$fieldName] = $this->pi_getLL('error_password');
				}

				// check password min length
				else if (strlen($this->piVars['password']) < $this->conf['password.']['minLength']) {
					$errors[$fieldName] = sprintf($this->pi_getLL('error_password_length'), $this->conf['password.']['minLength']);
				}

				// check if password contains enough numeric chars
				else if ($this->conf['password.']['minNumeric'] > 0) {
					$temp_check = str_split($this->piVars['password']);
					$temp_nums = 0;
					foreach ($temp_check as $check_num) {
						if (is_numeric($check_num)) {
							$temp_nums++;
						}
					}
					if ($temp_nums < $this->conf['password.']['minNumeric']) {
						$errors[$fieldName] = sprintf($this->pi_getLL('error_password_numerics'), $this->conf['password.']['minNumeric']);
					}
				}
			}

			// special processing of image field
			// process uploaded file?
			if ($fieldConf['type'] == 'image' && !empty($GLOBALS['_FILES'][$this->prefixId]['name'][$fieldName])) {
				$uploadData = $GLOBALS['_FILES'][$this->prefixId];
				$process = true;
				if ($uploadData['size'][$fieldName] > 0) {

					// file too big
					if ($uploadData['size'][$fieldName] > $this->conf['upload.']['maxFileSize']) {
						$errors[$fieldName] = sprintf($this->pi_getLL('error_upload_filesize'), $this->filesize_format($this->conf['upload.']['maxFileSize'], '', ''));
						$process = false;
					}

					// file type not allowed
					$allowedFileTypes = t3lib_div::trimExplode(',', strtolower($this->conf['upload.']['allowedFileTypes']));
					$dotPos = strpos($uploadData['name'][$fieldName], '.');
					$fileEnding = strtolower(substr($uploadData['name'][$fieldName], $dotPos + 1));
					if (!in_array($fileEnding, $allowedFileTypes)) {
						$errors[$fieldName] = sprintf($this->pi_getLL('error_upload_filetype'), $uploadData['name'][$fieldName]);
						$process = false;
					}
				}

				// write field if OK
				if ($process) {
					$uploadedFileName = $this->handleUpload($fieldName);
					if (!empty($uploadedFileName))
						$this->piVars[$fieldName] = $uploadedFileName;
					else
						$errors[$fieldName] = $this->pi_getLL('error_upload_no_success');
				}
			}

			// process new upload --> overwrite old file
			if ($fieldConf['type'] == 'image' && !empty($GLOBALS['_FILES'][$this->prefixId]['name'][$fieldName . '_new'])) {
				$uploadData = $GLOBALS['_FILES'][$this->prefixId];
				$process = true;
				if ($uploadData['size'][$fieldName . '_new'] > 0) {

					// file too big
					if ($uploadData['size'][$fieldName . '_new'] > $this->conf['upload.']['maxFileSize']) {
						$errors[$fieldName] = sprintf($this->pi_getLL('error_upload_filesize'), $this->filesize_format($this->conf['upload.']['maxFileSize'], '', ''));
						$process = false;
					}

					// file type not allowed
					$allowedFileTypes = t3lib_div::trimExplode(',', strtolower($this->conf['upload.']['allowedFileTypes']));
					$dotPos = strpos($uploadData['name'][$fieldName . '_new'], '.');
					$fileEnding = strtolower(substr($uploadData['name'][$fieldName . '_new'], $dotPos + 1));
					if (!in_array($fileEnding, $allowedFileTypes)) {
						$errors[$fieldName] = sprintf($this->pi_getLL('error_upload_filetype'), $uploadData['name'][$fieldName]);
						$process = false;
					}
				}

				// write field if OK
				if ($process) {
					$uploadedFileName = $this->handleUpload($fieldName . '_new');
					if (!empty($uploadedFileName))
						$this->piVars[$fieldName] = $uploadedFileName;
					else
						$errors[$fieldName] = $this->pi_getLL('error_upload_no_success');
				}
			}
		}

		// Hook for further evaluations
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tx_keuserregister']['specialEvaluations'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tx_keuserregister']['specialEvaluations'] as $_classRef) {
				$_procObj = & t3lib_div::getUserObj($_classRef);
				$_procObj->processSpecialEvaluations($errors, $this);
			}
		}

		// if errors occured: render form with error messages
		if (sizeof($errors))
			return $this->renderForm($errors);
		// otherwise: process form data
		else {
			// process edit form
			if ($this->mode == 'edit')
				return $this->processEditFormData();
			// process registration form
			else if ($this->mode == 'create')
				return $this->processRegistrationFormData();
		}
	}

	/**
	 * Process the form data for a registration
	 *
	 * @return void
	 */
	function processRegistrationFormData() {

		// check if storage page for records is defined
		if (!$this->conf['userDataPID'])
			die($this->prefixId . ': ERROR: No user data pid defined');

		// check if default usergroup is set
		if (!$this->conf['defaultUsergroup'])
			die($this->prefixId . ': ERROR: No default usergroup defined');

		// save fe_user with disabled=1
		$table = 'fe_users';
		$fields_values = array(
		    'pid' => intval($this->conf['userDataPID']),
		    'tstamp' => time(),
		    'disable' => 1,
		    'crdate' => time(),
		    'registerdate' => time(),
		    'usergroup' => $this->conf['defaultUsergroup'],
		);

		// process all defined fields
		foreach ($this->fields as $fieldName => $fieldConf) {
			$fieldName = str_replace('.', '', $fieldName);

			// special handling for directmail fields
			if ($fieldConf['type'] == 'directmail') {
				if (sizeof($this->piVars[$fieldName])) {
					foreach ($this->piVars[$fieldName] as $catUid => $value) {
						if ($value == 1)
							$dmailInsertValues[] = $catUid;
					}
				}
			} else if ($fieldConf['type'] == 'checkbox') {
				// special handling for multiple checkboxes
				$fieldValues = explode(',', $fieldConf['values']);
				if (count($fieldValues) > 1) {
					// multiple
					$valueSum = 0;
					if (is_array($this->piVars[$fieldName])) {
						foreach ($this->piVars[$fieldName] as $key => $val) {
							$valueSum += pow(2, $val - 1);
						}
					}
					$checkboxDBVal = intval($valueSum);
				} else {
					// single
					$checkboxDBVal = intval($this->piVars[$fieldName]);
				}
				$fields_values[$fieldName] = $this->lib->removeXSS($checkboxDBVal);
			} else if (!$fieldConf['doNotSaveInDB']) {
				// save all fields that are not marked as "doNotSaveInDB"
				$fields_values[$fieldName] = $this->lib->removeXSS($this->piVars[$fieldName]);
			}
		}


		// set name
		$fields_values['name'] = $this->lib->removeXSS($this->piVars['first_name'] . ' ' . $this->piVars['last_name']);

		// set email address as username if defined
		if ($this->conf['emailIsUsername'])
			$fields_values['username'] = $this->lib->removeXSS($this->piVars['email']);
		else
			$fields_values['username'] = $this->lib->removeXSS($this->piVars['username']);

		// encrypt password if defined in ts in $this->conf['password.']['encryption']
		$fields_values['password'] = $this->lib->encryptPassword($this->lib->removeXSS($this->piVars['password']), $this->conf['password.']['encryption']);

		// Hook for further data processing before saving to db
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tx_keuserregister']['specialDataProcessing'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tx_keuserregister']['specialDataProcessing'] as $_classRef) {
				$_procObj = & t3lib_div::getUserObj($_classRef);
				$_procObj->processSpecialDataProcessing($fields_values, $this);
			}
		}

		// save data to db an go on to further steps
		if ($GLOBALS['TYPO3_DB']->exec_INSERTquery($table, $fields_values)) {

			// new user's id
			$feuser_uid = $GLOBALS['TYPO3_DB']->sql_insert_id();

			// process directmail values
			if (is_array($dmailInsertValues)) {
				foreach ($dmailInsertValues as $key => $catUid) {
					$table = 'sys_dmail_feuser_category_mm';
					$fields_values = array(
					    'uid_local' => $feuser_uid,
					    'uid_foreign' => $catUid,
					);
					$GLOBALS['TYPO3_DB']->exec_INSERTquery($table, $fields_values);
				}
			}

			// Hook for further data processing after saving to db
			if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tx_keuserregister']['specialDataProcessingAfterSaveToDB'])) {
				foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tx_keuserregister']['specialDataProcessingAfterSaveToDB'] as $_classRef) {
					$_procObj = & t3lib_div::getUserObj($_classRef);
					$_procObj->processSpecialDataProcessingAfterSaveToDB($fields_values, $this, $feuser_uid);
				}
			}

			// process backlink params if activated
			$fields_values = array();
			if ($this->conf['backlink.']['generate']) {
				$fields_values['backlinkpid'] = intval($this->piVars['backlinkPid']);
				$fields_values['backlinkparams'] = t3lib_div::array2xml($this->getBacklinkParamsArray());
			}
			$hash = $this->generateHash($feuser_uid, '', $fields_values);

			// generate html mail content
			$htmlBody = $this->cObj->getSubpart($this->templateCode, '###CONFIRMATION_REQUEST###');

			// use salutation based on users gender
			$salutationCode = $this->piVars['gender'] == 1 ? 'female' : 'male';

			// generate confirmation link
			$linkconf['parameter'] = $GLOBALS['TSFE']->id;
			$linkconf['additionalParams'] = '&confirm=' . $hash;
			$confirmLinkUrl = t3lib_div::locationHeaderUrl($this->cObj->typoLink_URL($linkconf));
			$confirmationLink = '<a href="' . $confirmLinkUrl . '">' . $confirmLinkUrl . '</a>';

			// generate decline link
			unset($linkconf);
			$linkconf['parameter'] = $GLOBALS['TSFE']->id;
			$linkconf['additionalParams'] = '&decline=' . $hash;
			$declineLinkUrl = t3lib_div::locationHeaderUrl($this->cObj->typoLink_URL($linkconf));
			$declineLink = '<a href="' . $declineLinkUrl . '">' . $declineLinkUrl . '</a>';

			$markerArray = array(
			    'salutation' => $this->pi_getLL('salutation_' . $salutationCode),
			    'first_name' => $this->piVars['first_name'],
			    'last_name' => $this->piVars['last_name'],
			    'confirmation_request_text' => sprintf($this->pi_getLL('confirmation_request_text'), t3lib_div::getIndpEnv('TYPO3_SITE_URL')),
			    'confirmation_link' => $confirmationLink,
			    'decline_text' => $this->pi_getLL('decline_text'),
			    'decline_link' => $declineLink,
			    'farewell_text' => $this->pi_getLL('farewell_text'),
			    'site_url' => t3lib_div::getIndpEnv('TYPO3_SITE_URL'),
			    'hash' => $hash,
			);
			$htmlBody = $this->cObj->substituteMarkerArray($htmlBody, $markerArray, $wrap = '###|###', $uppercase = 1);

			// send double-opt-in-mail
			$subject = $this->pi_getLL('confirmation_request_subject');
			$this->sendNotificationEmail($this->piVars['email'], $subject, $htmlBody);

			// print message
			$content = $this->cObj->getSubpart($this->templateCode, '###FORM_SUCCESS_MESSAGE###');
			$markerArray = array(
			    'headline' => $this->pi_getLL('form_success_headline'),
			    'salutation' => $this->pi_getLL('salutation_' . $salutationCode),
			    'first_name' => $this->piVars['first_name'],
			    'last_name' => $this->piVars['last_name'],
			    'form_success_text' => $this->pi_getLL('form_success_text'),
			);
			$content = $this->cObj->substituteMarkerArray($content, $markerArray, $wrap = '###|###', $uppercase = 1);
			return $content;
		}
		else
			die($this->prefixId . ': ERROR: DATABASE ERROR WHEN SAVING RECORD');
	}

	/**
	 * Process the form data of an edit form
	 *
	 * @return void
	 */
	function processEditFormData() {

		// update fe user record
		$table = 'fe_users';
		$where = 'uid="' . intval($GLOBALS['TSFE']->fe_user->user['uid']) . '" ';

		foreach ($this->fields as $fieldName => $fieldConf) {

			$fieldName = str_replace('.', '', $fieldName);

			// special handling for directmail fields
			if ($fieldConf['type'] == 'directmail') {
				if (sizeof($this->piVars[$fieldName])) {
					foreach ($this->piVars[$fieldName] as $catUid => $value) {
						if ($value == 1)
							$dmailInsertValues[] = $catUid;
					}
				}
			} else if ($fieldConf['type'] == 'checkbox') {
				// special handling for multiple checkboxes
				$fieldValues = explode(',', $fieldConf['values']);
				if (count($fieldValues) > 1) {
					// multiple
					$valueSum = 0;
					if (is_array($this->piVars[$fieldName])) {
						foreach ($this->piVars[$fieldName] as $key => $val) {
							$valueSum += pow(2, $val - 1);
						}
					}
					$checkboxDBVal = intval($valueSum);
				} else {
					// single
					$checkboxDBVal = intval($this->piVars[$fieldName]);
				}
				$fields_values[$fieldName] = $this->lib->removeXSS($checkboxDBVal);
			} else if (!$fieldConf['doNotSaveInDB']) {
				// save all fields that are not marked as "doNotSaveInDB"
				$fields_values[$fieldName] = $this->lib->removeXSS($this->piVars[$fieldName]);
			}
		}

		// Hook for further data processing before saving to db
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tx_keuserregister']['specialDataProcessing'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tx_keuserregister']['specialDataProcessing'] as $_classRef) {
				$_procObj = &t3lib_div::getUserObj($_classRef);
				$_procObj->processSpecialDataProcessing($fields_values, $this);
			}
		}

		// modify tstamp value to current time
		$fields_values['tstamp'] = time();

		// do not process email change here
		if ($this->mode == 'edit')
			unset($fields_values['email']);

		// save data in db
		if ($GLOBALS['TYPO3_DB']->exec_UPDATEquery($table, $where, $fields_values, $no_quote_fields = FALSE)) {

			// delete all mm entries in db
			$GLOBALS['TYPO3_DB']->exec_DELETEquery('sys_dmail_feuser_category_mm', 'uid_local="' . $GLOBALS['TSFE']->fe_user->user['uid'] . '"');

			// process directmail values
			if (is_array($dmailInsertValues)) {
				foreach ($dmailInsertValues as $key => $catUid) {
					$table = 'sys_dmail_feuser_category_mm';
					$fields_values = array(
					    'uid_local' => $GLOBALS['TSFE']->fe_user->user['uid'],
					    'uid_foreign' => $catUid,
					);
					$GLOBALS['TYPO3_DB']->exec_INSERTquery($table, $fields_values, $no_quote_fields = FALSE);
				}
			}


			$content = $this->cObj->getSubpart($this->templateCode, '###SUB_MESSAGE###');
			$content = $this->cObj->substituteMarker($content, '###HEADLINE###', $this->pi_getLL('edit_success_headline'));
			#$content = $this->cObj->substituteMarker($content,'###MESSAGE###',$this->pi_getLL('edit_success_text'));

			if ($this->conf['adminMailAfterEdit'] == 1) {
				$text = $this->pi_getLL('admin_mail_text_userdata_change');
				$subject = $this->pi_getLL('admin_mail_subject_userdata_change');
				$this->sendAdminMail($GLOBALS['TSFE']->fe_user->user['uid'], $subject, $text);
			}

			// email has changed
			// check if valid and not already used in db
			// do not process until user confirms the change
			// save new value in hash table and send email
			// with confirmation link to the user
			if ($this->emailHasChanged()) {
				// save new value in hash table
				$hashTable = 'tx_keuserregister_hash';
				$this->emailChangeHash = $this->getUniqueCode();
				$hashFieldsValues = array(
				    'hash' => $this->emailChangeHash,
				    'feuser_uid' => intval($GLOBALS['TSFE']->fe_user->user['uid']),
				    'tstamp' => time(),
				    'new_email' => $this->lib->removeXSS($this->piVars['email']),
				);
				$GLOBALS['TYPO3_DB']->exec_INSERTquery($hashTable, $hashFieldsValues);

				// send email confirmation request to user's new email address
				$this->sendEmailChangeConfirmationRequestMail();

				// print success message
				$content = $this->cObj->substituteMarker($content, '###MESSAGE###', $this->pi_getLL('edit_sucess_text_email_change'));
			}
			else
				$content = $this->cObj->substituteMarker($content, '###MESSAGE###', $this->pi_getLL('edit_success_text'));

			return $content;
		}
		else
			die($this->prefixId . ': ERROR: DB error when saving data');
	}

	/**
	 * Send the confirmation request mail for changing
	 * the user's email address
	 *
	 * @return void
	 */
	function sendEmailChangeConfirmationRequestMail() {
		// generate html mail content
		$htmlBody = $this->cObj->getSubpart($this->templateCode, '###CONFIRMATION_REQUEST###');

		// use salutation based on users gender
		$salutationCode = $this->piVars['gender'] == 1 ? 'female' : 'male';

		// generate confirmation link
		$linkconf['parameter'] = $GLOBALS['TSFE']->id;
		$linkconf['additionalParams'] = '&mailconfirm=' . $this->emailChangeHash;
		$confirmLinkUrl = t3lib_div::locationHeaderUrl($this->cObj->typoLink_URL($linkconf));
		$confirmationLink = '<a href="' . $confirmLinkUrl . '">' . $confirmLinkUrl . '</a>';

		// generate decline link
		unset($linkconf);
		$linkconf['parameter'] = $GLOBALS['TSFE']->id;
		$linkconf['additionalParams'] = '&maildecline=' . $this->emailChangeHash;
		$declineLinkUrl = t3lib_div::locationHeaderUrl($this->cObj->typoLink_URL($linkconf));
		$declineLink = '<a href="' . $declineLinkUrl . '">' . $declineLinkUrl . '</a>';

		$markerArray = array(
		    'salutation' => $this->pi_getLL('salutation_' . $salutationCode),
		    'first_name' => $this->piVars['first_name'],
		    'last_name' => $this->piVars['last_name'],
		    'confirmation_request_text' => sprintf($this->pi_getLL('mail_confirmation_request_text'), t3lib_div::getIndpEnv('TYPO3_SITE_URL')),
		    'confirmation_link' => $confirmationLink,
		    'decline_text' => $this->pi_getLL('mail_decline_text'),
		    'decline_link' => $declineLink,
		    'farewell_text' => $this->pi_getLL('farewell_text'),
		    'site_url' => t3lib_div::getIndpEnv('TYPO3_SITE_URL'),
		);
		$htmlBody = $this->cObj->substituteMarkerArray($htmlBody, $markerArray, $wrap = '###|###', $uppercase = 1);

		// send double-opt-in-mail
		$subject = $this->pi_getLL('mail_confirmation_request_subject');
		$this->sendNotificationEmail($this->piVars['email'], $subject, $htmlBody);
	}

	/**
	 * Checks, wethe user email address has changed
	 *
	 * @return bool
	 */
	function emailHasChanged() {
		$fields = '*';
		$table = 'fe_users';
		$where = 'uid="' . intval($GLOBALS['TSFE']->fe_user->user['uid']) . '" ';
		$where .= $this->cObj->enableFields($table);
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields, $table, $where, $groupBy = '', $orderBy = '', $limit = '1');
		$anz = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
		$userRow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		if ($userRow['email'] != $this->lib->removeXSS($this->piVars['email']))
			return true;
		else
			return false;
	}

	/**
	 * Creates a unique hash code
	 *
	 * @param integer $length
	 * @return string
	 */
	function getUniqueCode($length = 8) {
		$code = md5(uniqid(rand(), true));
		if ($length != "")
			$codeString = substr($code, 0, $length);
		else
			$codeString = $code;

		// check if hash already existent in db
		$fields = '*';
		$table = 'tx_keuserregister_hash';
		$where = 'hash="' . $codeString . '"';
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields, $table, $where, $groupBy = '', $orderBy = '', $limit = '');
		$anz = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
		if (!$anz)
			return $codeString;
		else
			return $this->getUniqueCode();
	}

	/**
	 * Check if value is an unsigned int
	 *
	 * @param mixed	value that has to be checked
	 * @return bool
	 */
	function is_unsigned_int($val) {
		return ctype_digit((string) $value);
	}

	/**
	 * sends the notification email, uses the TYPO3 mail functions
	 *
	 * @param string $toEMail
	 * @param string $subject
	 * @param string $html_body
	 * @param int $sendAsHTML
	 * @access public
	 * @return void
	 */
	public function sendNotificationEmail($toEMail, $subject, $html_body, $sendAsHTML = 1) {

		// Only ASCII is allowed in the header
		$subject = html_entity_decode(t3lib_div::deHSCentities($subject), ENT_QUOTES, $GLOBALS['TSFE']->renderCharset);
		$subject = t3lib_div::encodeHeader($subject, 'base64', $GLOBALS['TSFE']->renderCharset);

		// add the footer
		if ($this->conf['notification.']['addFooter']) {
			$html_body .= $this->getMailFooter();
		}

		// create the plain message body
		$message = html_entity_decode(strip_tags($html_body), ENT_QUOTES, $GLOBALS['TSFE']->renderCharset);

		// use new mail api if T3 v >= 4.5
		if (t3lib_utility_VersionNumber::convertVersionNumberToInteger(TYPO3_version) >= 4005000) {
			$Typo3_htmlmail = t3lib_div::makeInstance('t3lib_mail_Message');
			$Typo3_htmlmail->setFrom(
				array($this->conf['notification.']['from_email'] => $this->conf['notification.']['from_name'])
			);
			$Typo3_htmlmail->setTo(explode(',', $toEMail));
			$Typo3_htmlmail->setSubject($subject);
			if ($sendAsHTML)
				$Typo3_htmlmail->setBody($html_body, 'text/html');
			if ($message)
				$Typo3_htmlmail->addPart($message, 'text/plain');
			$Typo3_htmlmail->send();
		} else {
			// use old mail api if T3 v < 4.5
			// inspired by code from tt_products, thanks
			$Typo3_htmlmail = t3lib_div::makeInstance('t3lib_htmlmail');
			$Typo3_htmlmail->start();
			$Typo3_htmlmail->subject = $subject;
			$Typo3_htmlmail->from_email = $this->conf['notification.']['from_email'];
			$Typo3_htmlmail->from_name = $this->conf['notification.']['from_name'];
			$Typo3_htmlmail->replyto_email = $Typo3_htmlmail->from_email;
			$Typo3_htmlmail->replyto_name = $Typo3_htmlmail->from_name;
			$Typo3_htmlmail->organisation = '';
			if ($sendAsHTML) {
				$Typo3_htmlmail->theParts['html']['content'] = $html_body;
				$Typo3_htmlmail->theParts['html']['path'] = t3lib_div::getIndpEnv('TYPO3_REQUEST_HOST') . '/';
				$Typo3_htmlmail->extractMediaLinks();
				$Typo3_htmlmail->extractHyperLinks();
				$Typo3_htmlmail->fetchHTMLMedia();
				$Typo3_htmlmail->substMediaNamesInHTML(0); // 0 = relative
				$Typo3_htmlmail->substHREFsInHTML();
				$Typo3_htmlmail->setHTML($Typo3_htmlmail->encodeMsg($Typo3_htmlmail->theParts['html']['content']));
				if ($message)
					$Typo3_htmlmail->addPlain($message);
			}
			else
				$Typo3_htmlmail->addPlain($message);
			$Typo3_htmlmail->setHeaders();
			$Typo3_htmlmail->setContent();
			$Typo3_htmlmail->setRecipient(explode(',', $toEMail));
			$Typo3_htmlmail->sendTheMail();
		}
	}

	/**
	 * renders the mail footer
	 * 
	 * @return string
	 */
	public function getMailFooter() {
		$template = $this->cObj->getSubpart($this->templateCode, '###MAIL_FOOTER###');
		$markerArray = array(
		    'mailfooter_name' => $this->pi_getLL('mailfooter_name')
		);
		return $this->cObj->substituteMarkerArray($template, $markerArray, '###|###', 1);
	}

	/**
	 * Uploads the file given in the form-field $attachmentName to the server
	 *
	 * success: returns the new filename
	 * no success: returns false
	 *
	 * @param string $attachmentName
	 * @return array
	 */
	public function handleUpload($fieldName) {
		$success = true;

		// get the destination filename
		$filefuncs = new t3lib_basicFilefunctions();
		$uploadfile = $filefuncs->getUniqueName($filefuncs->cleanFileName($GLOBALS['_FILES'][$this->prefixId]['name'][$fieldName]), $this->fileUploadDir);

		if ($success && move_uploaded_file($GLOBALS['_FILES'][$this->prefixId]['tmp_name'][$fieldName], $uploadfile)) {
			// change rights so that everyone can read the file
			chmod($uploadfile, octdec('0744'));
		} else {
			$error = $this->pi_getLL('error_file_upload_not_successful', 'Error: File upload was not successfull.');
			$success = false;
		}

		if ($success)
			return basename($uploadfile);
		else
			return '';
	}

	/**
	 * Format a number of bytes into a human readable format.
	 * Optionally choose the output format and/or force a particular unit
	 *
	 * @param   int     $bytes      The number of bytes to format. Must be positive
	 * @param   string  $format     Optional. The output format for the string
	 * @param   string  $force      Optional. Force a certain unit. B|KB|MB|GB|TB
	 * @return  string              The formatted file size
	 */
	function filesize_format($bytes, $format = '', $force = '') {
		$force = strtoupper($force);
		$defaultFormat = '%01d %s';
		if (strlen($format) == 0)
			$format = $defaultFormat;
		$bytes = max(0, (int) $bytes);
		$units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
		$power = array_search($force, $units);
		if ($power === false)
			$power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
		return sprintf($format, $bytes / pow(1024, $power), $units[$power]);
	}

	/*
	 * function getUserRecord
	 * @param int $userId
	 * @param bool $ignoreEnableFields (used for fetching users which are not confirmed yet)
	 */
	function getUserRecord($userId, $ignoreEnableFields=false) {
		$fields = '*';
		$table = 'fe_users';
		$where = 'uid="' . intval($userId) . '" ';
		if (!$ignoreEnableFields) {
			$where .= $this->cObj->enableFields($table);
		}

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields, $table, $where, '', '', '1');
		if (!$GLOBALS['TYPO3_DB']->sql_num_rows($res)) {
			return false;
		} else {
			return $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		}
	}

	/*
	 * function autoLogin
	 *
	 * automatically login a user by code
	 * @param string $username
	 * @param string $password
	 * @return bool	 true if login successful, false if login failed
	 */
	function autoLogin($username, $password) {

		$loginData = array(
		    'uname' => $username, //username
		    'uident' => $password, //password
		    'status' => 'login',
		);

		$GLOBALS['TSFE']->fe_user->checkPid = 0; //do not use a particular pid
		$GLOBALS['TSFE']->fe_user->user = $GLOBALS['TSFE']->fe_user->fetchUserSession();
		$GLOBALS['TSFE']->fe_user->fetchGroupData();
		$info = $GLOBALS['TSFE']->fe_user->getAuthInfoArray();
		$user = $GLOBALS['TSFE']->fe_user->fetchUserRecord($info['db_user'], $loginData['uname']);

		$ok = $GLOBALS['TSFE']->fe_user->compareUident($user, $loginData);
		if ($ok) {
			//login successfull
			$GLOBALS['TSFE']->fe_user->createUserSession($user);
			return true;
		} else {
			//login failed
			return false;
		}
	}

	/**
	 * function sendAdminMail
	 *
	 * @param $userUid
	 * @param $adminMailSubject
	 * @param $adminMailText
	 * @return void
	 */
	function sendAdminMail($userUid, $adminMailSubject, $adminMailText, $adminMailTextBelow = '') {

		$userData = $this->getUserRecord($userUid, true);

		$htmlBody = $this->cObj->getSubpart($this->templateCode, '###ADMIN_MAIL_BODY###');
		$htmlField = $this->cObj->getSubpart($this->templateCode, '###ADMIN_MAIL_SUB###');

		$mailMarkerArray = array();
		$mailMarkerArray['admin_mail_field_values'] = '';
		if ($this->conf['adminMailFields']) {
			$fields = t3lib_div::trimExplode(',', $this->conf['adminMailFields'], true);
			foreach ($fields as $singleField) {
				$markerArrayField = array();
				$markerArrayField['###LABEL###'] = $this->pi_getLL('label_' . $singleField);
				$markerArrayField['###VALUE###'] = $this->renderFieldAsText($singleField, $userData[$singleField]);
				$mailMarkerArray['admin_mail_field_values'] .= $this->cObj->substituteMarkerArray($htmlField, $markerArrayField);
			}
		}

		$mailMarkerArray['admin_mail_text'] = $adminMailText;
		$mailMarkerArray['admin_mail_text_below'] = $adminMailTextBelow;

		$subject = $adminMailSubject;
		foreach ($this->conf['adminMail.'] as $admins) {
			$htmlBody = $this->cObj->substituteMarkerArray($htmlBody, $mailMarkerArray, $wrap = '###|###', $uppercase = 1);
			$this->sendNotificationEmail($admins['address'], $subject, $htmlBody);
		}
	}

	/**
	 * @param string $fieldName name of the database field
	 * @param string $fieldContent content of the database field
	 * @return string value to show in the email
	 */
	function renderFieldAsText($fieldName, $fieldContent) {

		$value = '';
		switch ($this->fields[$fieldName . '.']['type']) {
			case 'textarea':
			case 'text':
			case 'yearofbirth':
			case 'monthofbirth':
			case 'dayofbirth':
			case 'country':
				$value = $fieldContent;
				break;
			case 'radio':
			case 'select':
				$value = $this->pi_getLL('label_' . $fieldName . '_' . trim($fieldContent));
				if ($value == '') {
					$value = $fieldContent;
				}
				break;
			case 'checkbox':
				$value = '';
				$fieldValues = explode(',', $this->fields[$fieldName . '.']['values']);
				foreach ($fieldValues as $key => $singleValue) {
					if (($fieldContent >> $singleValue - 1) & 1) {
						if ($value != '') {
							$value .= ', ';
						}
						$value .= $this->pi_getLL('label_' . $fieldName . '_' . $singleValue);
					}
				}
				break;
			case 'hidden':
				// do not send anything; should we?
				break;
			case 'image':
				// TODO
				break;
			case 'select_db_relation':
				// TODO
				break;
			case 'directmail':
				// TODO
				break;
			default:
				// What makes sense?
				break;
		}

		return $value;
	}

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_userregister/pi1/class.tx_keuserregister_pi1.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_userregister/pi1/class.tx_keuserregister_pi1.php']);
}
?>
