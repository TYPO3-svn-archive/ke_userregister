<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009 Andreas Kiefer <kiefer@kennziffer.com>
*  All rights reserved
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
***************************************************************/
/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 * Hint: use extdeveval to insert/update function index above.
 */

require_once(PATH_tslib.'class.tslib_pibase.php');
require_once(PATH_t3lib.'class.t3lib_htmlmail.php');
require_once(PATH_t3lib.'class.t3lib_basicfilefunc.php');
require_once(t3lib_extMgm::extPath('ke_userregister', 'lib/class.tx_keuserregister_lib.php'));

/**
 * Plugin 'Register Form' for the 'ke_userregister' extension.
 *
 * @author	Andreas Kiefer <kiefer@kennziffer.com>
 * @package	TYPO3
 * @subpackage	tx_keuserregister
 */
class tx_keuserregister_pi1 extends tslib_pibase {
	var $prefixId      = 'tx_keuserregister_pi1';		// Same as class name
	var $scriptRelPath = 'pi1/class.tx_keuserregister_pi1.php';	// Path to this script relative to the extension dir.
	var $extKey        = 'ke_userregister';	// The extension key.

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
		$this->pi_USER_INT_obj = 1;	// Configuring so caching is not expected. This value means that no cHash params are ever set. We do this, because it's a USER_INT object!

		// get tooltip class if extension is installed
		if (t3lib_extMgm::isLoaded('fe_tooltip')) {
			require_once(t3lib_extMgm::extPath('fe_tooltip').'class.tx_fetooltip.php');
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
			foreach ( $piFlexForm['data'] as $sheet => $data ) {
				foreach ( $data as $lang => $value ) {
					foreach ( $value as $key => $val ) {
						$this->ffdata[$key] = $this->pi_getFFvalue($piFlexForm, $key, $sheet);
					}
				}
			}
		}

		// plugin mode: ts value overwrites ff value
		if (isset($this->conf['mode'])) $this->mode = $this->conf['mode'];
		else {
			switch ($this->ffdata['mode']) {
				case 0: $this->mode = 'create'; break;
				case 1: $this->mode = 'edit'; break;
			}
		}

		// get fields from configuration
		$this->fields = $this->conf[$this->mode.'.']['fields.'];

		// overwrite username config if email is used as username
		if ($this->conf['emailIsUsername']) unset($this->fields['username.']);

		// overwrite password field if edit mode is set
		if ($this->mode == 'edit') unset($this->fields['password.']);

		// get html template
		$this->templateCode = $this->cObj->fileResource($this->conf['templateFile']);

		// include css
		if ($this->conf['cssFile']) {
			$GLOBALS['TSFE']->additionalHeaderData[$this->prefixId] .= '<link rel="stylesheet" type="text/css" href="'.$this->conf['cssFile'].'" />';
		}

		// process incoming registration confirmation
		if (t3lib_div::_GET('confirm')) $content = $this->processConfirm();
		// process incoming registration decline
		else if (t3lib_div::_GET('decline')) $content = $this->processDecline();
		// process incoming email change confirmation
		else if (t3lib_div::_GET('mailconfirm')) $content = $this->processEmailChangeConfirm();
		// process incoming email change decline
		else if (t3lib_div::_GET('maildecline')) $content = $this->processEmailChangeDecline();
		// show registration / edit form
		else $content = $this->piVars['step'] == 'evaluate' ? $this->evaluateFormData() : $this->renderForm();

		return $this->pi_wrapInBaseClass($content);
	}



	/**
	* Description
	*
	* @param	type		desc
	* @return	The content that is displayed on the website
	*/
	function processConfirm() {
		// check if hash duration is set
		if (!$this->conf['hashDays']) die($this->prefixId.': ERROR: hash duration is not set');

		// generate timestamp for checking hash age
		$tstampCalculated = time() - ($this->conf['hashDays'] * (60 * 60 * 24));

		// select from hash table
		$fields = '*';
		$table = 'tx_keuserregister_hash';
		$hashCompare = t3lib_div::removeXSS(t3lib_div::_GET('confirm'));
		$where = 'hash="'.$hashCompare.'" ';
		$where .= 'and tstamp>'.$tstampCalculated.'  ';
		$hashRes = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='');
		$anz = $GLOBALS['TYPO3_DB']->sql_num_rows($hashRes);
		// if number of records not eq 1: ERROR!
		if ($anz != 1) {
			// print error message
			$content = $this->cObj->getSubpart($this->templateCode,'###SUB_MESSAGE###');
			$markerArray = array(
				'headline' => $this->pi_getLL('confirmation_error_headline'),
				'message' => sprintf($this->pi_getLL('confirmation_error_message'),t3lib_div::getIndpEnv('TYPO3_SITE_URL')),
			);
			$content = $this->cObj->substituteMarkerArray($content,$markerArray,$wrap='###|###',$uppercase=1);
			return $content;
		}
		// if number of found records is eq 1: activate user record
		else {
			$hashRow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($hashRes);
			$table = 'fe_users';
			$where = 'uid="'.intval($hashRow['feuser_uid']).'" ';
			$fields_values = array('disable' => 0, 'tstamp' => time());
			// delete hash after processing
			if ($GLOBALS['TYPO3_DB']->exec_UPDATEquery($table,$where,$fields_values,$no_quote_fields=FALSE)) {
				$this->deleteHashEntry($hashRow['hash']);
			}
			// print success message
			$content = $this->cObj->getSubpart($this->templateCode,'###SUB_MESSAGE###');
			$markerArray = array(
				'headline' => $this->pi_getLL('confirmation_success_headline'),
				'message' => sprintf($this->pi_getLL('confirmation_success_message'),t3lib_div::getIndpEnv('TYPO3_SITE_URL')),
			);
			// send success e-mail
			$this->sendConfirmationSuccessMail($hashRow['feuser_uid']);

			$content = $this->cObj->substituteMarkerArray($content,$markerArray,$wrap='###|###',$uppercase=1);
			return $content;
		}
	}



	/*
	 * function sendConfirmationSuccessMail
	 * @param $userUid int
	 */
	function sendConfirmationSuccessMail($userUid) {
		$fields = '*';
		$table = 'fe_users';
		$where = 'uid="'.intval($userUid).'" ';
		$where .= $this->cObj->enableFields($table);
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='1');
		while ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			// use salutation based on users gender
			$salutationCode = $row['gender'] == 1 ? 'female' : 'male';

			$htmlBody = $this->cObj->getSubpart($this->templateCode,'###CONFIRMATION_SUCCESS_MAIL###');
			$mailMarkerArray = array(
				'salutation' => $this->pi_getLL('salutation_'.$salutationCode),
				'first_name' => $row['first_name'],
				'last_name' => $row['last_name'],
				'confirmation_success_text' => $this->pi_getLL('confirmation_success_text'),
				'farewell_text' => $this->pi_getLL('farewell_text'),
				'site_url' => t3lib_div::getIndpEnv('TYPO3_SITE_URL'),
			);
			$htmlBody = $this->cObj->substituteMarkerArray($htmlBody,$mailMarkerArray,$wrap='###|###',$uppercase=1);
			$subject = $this->pi_getLL('confirmation_success_subject');
			$this->sendNotificationEmail($row['email'], $subject, $htmlBody);
		}
	}

	/**
	* Description
	*
	* @param	type		desc
	* @return	The content that is displayed on the website
	*/
	function processDecline() {

		// check if hash duration is set
		if (!$this->conf['hashDays']) die($this->prefixId.': ERROR: no hash duration is not set');

		// generate timestamp for checking hash age
		$tstampCalculated = time() - ($this->conf['hashDays'] * (60 * 60 * 24));

		// select from hash table
		$fields = '*';
		$table = 'tx_keuserregister_hash';
		$hashCompare = t3lib_div::removeXSS(t3lib_div::_GET('decline'));
		$where = 'hash="'.$hashCompare.'" ';
		$where .= 'and tstamp>'.$tstampCalculated.'  ';
		$hashRes = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='');
		$anz = $GLOBALS['TYPO3_DB']->sql_num_rows($hashRes);
		// if number of records not eq 1: ERROR!
		if ($anz != 1) {
			// print error message
			$content = $this->cObj->getSubpart($this->templateCode,'###SUB_MESSAGE###');
			$markerArray = array(
				'headline' => $this->pi_getLL('decline_error_headline'),
				'message' => $this->pi_getLL('decline_error_message'),
			);
			$content = $this->cObj->substituteMarkerArray($content,$markerArray,$wrap='###|###',$uppercase=1);
			return $content;
		}
		// if number of found records is eq 1: completely delete user record
		else {
			$hashRow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($hashRes);
			$table = 'fe_users';
			$where = 'uid="'.intval($hashRow['feuser_uid']).'" ';
			$fields_values = array('disable' => 0, 'tstamp' => time());
			// delete hash after processing
			if ($GLOBALS['TYPO3_DB']->exec_DELETEquery($table,$where)) 	$this->deleteHashEntry($hashRow['hash']);
			// print success message
			$content = $this->cObj->getSubpart($this->templateCode,'###SUB_MESSAGE###');
			$markerArray = array(
				'headline' => $this->pi_getLL('decline_success_headline'),
				'message' => $this->pi_getLL('decline_success_message'),
			);
			$content = $this->cObj->substituteMarkerArray($content,$markerArray,$wrap='###|###',$uppercase=1);
			return $content;
		}
	}

	/**
	* Description
	*
	* @param	type		desc
	* @return	The content that is displayed on the website
	*/
	function processEmailChangeConfirm() {
		// check if hash duration is set
		if (!$this->conf['hashDays']) die($this->prefixId.': ERROR: hash duration is not set');

		// generate timestamp for checking hash age
		$tstampCalculated = time() - ($this->conf['hashDays'] * (60 * 60 * 24));

		// select from hash table
		$fields = '*';
		$table = 'tx_keuserregister_hash';
		$hashCompare = t3lib_div::removeXSS(t3lib_div::_GET('mailconfirm'));
		$where = 'hash="'.$hashCompare.'" ';
		$where .= 'and tstamp>'.$tstampCalculated.'  ';
		$hashRes = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='');
		$anz = $GLOBALS['TYPO3_DB']->sql_num_rows($hashRes);
		// if number of records not eq 1: ERROR!
		if ($anz != 1) {
			// print error message
			$content = $this->cObj->getSubpart($this->templateCode,'###SUB_MESSAGE###');
			$markerArray = array(
				'headline' => $this->pi_getLL('mail_confirmation_error_headline'),
				'message' => sprintf($this->pi_getLL('mail_confirmation_error_message'),t3lib_div::getIndpEnv('TYPO3_SITE_URL')),
			);
			$content = $this->cObj->substituteMarkerArray($content,$markerArray,$wrap='###|###',$uppercase=1);
			return $content;
		}
		// if number of found records is eq 1: activate user record
		else {
			$hashRow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($hashRes);

			// update fe user record with new email address
			$table = 'fe_users';
			$where = 'uid="'.intval($hashRow['feuser_uid']).'" ';
			$fields_values['tstamp'] = time();
			$fields_values['email'] = t3lib_div::removeXSS($hashRow['new_email']);

			// set username too if email is used as username
			if ($this->conf['emailIsUsername']) $fields_values['username'] = t3lib_div::removeXSS($hashRow['new_email']);

			// delete hash after processing
			if ($GLOBALS['TYPO3_DB']->exec_UPDATEquery($table,$where,$fields_values,$no_quote_fields=FALSE)) {
				$this->deleteHashEntry($hashRow['hash']);
			}
			// print success message
			$content = $this->cObj->getSubpart($this->templateCode,'###SUB_MESSAGE###');
			$markerArray = array(
				'headline' => $this->pi_getLL('mail_confirmation_success_headline'),
				'message' => sprintf($this->pi_getLL('mail_confirmation_success_message'),t3lib_div::getIndpEnv('TYPO3_SITE_URL')),
			);
			$content = $this->cObj->substituteMarkerArray($content,$markerArray,$wrap='###|###',$uppercase=1);
			return $content;
		}
	}

	/**
	* Description
	*
	* @param	type		desc
	* @return	The content that is displayed on the website
	*/
	function processEmailChangeDecline() {

		// check if hash duration is set
		if (!$this->conf['hashDays']) die($this->prefixId.': ERROR: no hash duration is not set');

		// generate timestamp for checking hash age
		$tstampCalculated = time() - ($this->conf['hashDays'] * (60 * 60 * 24));

		// select from hash table
		$fields = '*';
		$table = 'tx_keuserregister_hash';
		$hashCompare = t3lib_div::removeXSS(t3lib_div::_GET('maildecline'));
		$where = 'hash="'.$hashCompare.'" ';
		$where .= 'and tstamp>'.$tstampCalculated.'  ';
		$hashRes = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='');
		$anz = $GLOBALS['TYPO3_DB']->sql_num_rows($hashRes);
		// if number of records not eq 1: ERROR!
		if ($anz != 1) {
			// print error message
			$content = $this->cObj->getSubpart($this->templateCode,'###SUB_MESSAGE###');
			$markerArray = array(
				'headline' => $this->pi_getLL('mail_decline_error_headline'),
				'message' => $this->pi_getLL('mail_decline_error_message'),
			);
			$content = $this->cObj->substituteMarkerArray($content,$markerArray,$wrap='###|###',$uppercase=1);
			return $content;
		}
		// if number of found records is eq 1: completely delete user record
		else {
			// delete hash after processing
			$this->deleteHashEntry(t3lib_div::_GET('maildecline'));
			// print success message
			$content = $this->cObj->getSubpart($this->templateCode,'###SUB_MESSAGE###');
			$markerArray = array(
				'headline' => $this->pi_getLL('mail_decline_success_headline'),
				'message' => $this->pi_getLL('mail_decline_success_message'),
			);
			$content = $this->cObj->substituteMarkerArray($content,$markerArray,$wrap='###|###',$uppercase=1);
			return $content;
		}
	}

	/**
	* Description
	*
	* @param	type		desc
	* @return	The content that is displayed on the website
	*/
	function deleteHashEntry($hash) {
		$table = 'tx_keuserregister_hash';
		$hashCompare = t3lib_div::removeXSS($hash);
		$where = 'hash="'.$hashCompare.'" ';
		$GLOBALS['TYPO3_DB']->exec_DELETEquery($table,$where);
	}

	/**
	* Renders the registration form
	*
	* @param	type		desc
	* @return	The content that is displayed on the website
	*/
	function renderForm($errors=array()) {

		// initial checks
		// edit profile and no login
		if ($this->mode == 'edit' && !$GLOBALS['TSFE']->loginUser) {
			$content = $this->cObj->getSubpart($this->templateCode,'###SUB_MESSAGE###');
			$content = $this->cObj->substituteMarker($content,'###HEADLINE###',$this->pi_getLL('no_login_headline'));
			$content = $this->cObj->substituteMarker($content,'###MESSAGE###',sprintf($this->pi_getLL('no_login_message'),$GLOBALS['TSFE']->fe_user->user['username']));
			return $content;
		}

		// user already logged in
		else if ($this->mode != 'edit' && $GLOBALS['TSFE']->loginUser) {
			$content = $this->cObj->getSubpart($this->templateCode,'###SUB_MESSAGE###');
			$content = $this->cObj->substituteMarker($content,'###HEADLINE###',$this->pi_getLL('already_logged_in_headline'));
			$content = $this->cObj->substituteMarker($content,'###MESSAGE###',sprintf($this->pi_getLL('already_logged_in_message'),$GLOBALS['TSFE']->fe_user->user['username']));
			return $content;
		}

		// get general markers
		$this->markerArray = $this->getGeneralMarkers();

		// get data from db when editing profile
		if ($this->mode == 'edit') {
			$fields = '*';
			$table = 'fe_users';
			$where = 'uid="'.intval($GLOBALS['TSFE']->fe_user->user['uid']).'" ';
			$where .= $this->cObj->enableFields($table);
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='1');
			$anz = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
			$userRow=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);

			// set db value as piVar value when not already sent by the form
			foreach ($this->fields as $fieldName => $fieldConf) {
				$fieldName = str_replace('.','',$fieldName);

				// special handling for checkboxes
				// process empty post value
				if ($fieldConf['type'] == 'checkbox') {
					// form not sent yet
					if (!isset($this->piVars['step'])) $this->piVars[$fieldName] = $userRow[$fieldName];
				}

				// direct mail
				else if ($fieldConf['type'] == 'directmail') {
					if (!isset($this->piVars[$fieldName])) {
						// get directmail values from db
						$this->dmailValues = array();
						$fields = 'uid_local,uid_foreign';
						$table = 'sys_dmail_feuser_category_mm';
						$where = 'uid_local="'.intval($GLOBALS['TSFE']->fe_user->user['uid']).'" ';
						$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='');
						$anz = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
						while ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
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
				}
				else if (!isset($this->piVars[$fieldName])) {
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
						foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tx_keuserregister']['prefillValue_' . $fieldName] as $_classRef) {
							$_procObj = & t3lib_div::getUserObj($_classRef);
							$this->piVars[$fieldName] = $_procObj->generatePrefillValue($this->piVars[$fieldName],$this);
						}
					}
				}
			}
		}

		foreach ($this->fields as $fieldName => $fieldConf) {
			$fieldName = str_replace('.','',$fieldName);

			$this->markerArray['label_'.$fieldName] = $this->pi_getLL('label_'.$fieldName);
			$this->markerArray['value_'.$fieldName] = $this->piVars[$fieldName];

			// mark field as required
			if (strstr($fieldConf['eval'], 'required')) $this->markerArray['label_'.$fieldName] .= $this->cObj->getSubpart($this->templateCode,'###SUB_REQUIRED###');

			// render input field
			$this->markerArray['input_'.$fieldName] = $this->renderInputField($fieldConf,$fieldName);

			// wrap input field if error occured
			if ($errors[$fieldName]) {
				$this->markerArray['input_'.$fieldName] =
					$this->cObj->getSubpart($this->templateCode,'###SUB_ERRORWRAP_BEGIN###')
					. $this->markerArray['input_'.$fieldName]
					. $this->cObj->getSubpart($this->templateCode,'###SUB_ERRORWRAP_END###');
			}

			// mark field when errors occured
			if ($errors[$fieldName]) $this->markerArray['error_'.$fieldName] = $errors[$fieldName];
			else $this->markerArray['error_'.$fieldName] = '';
		}

		// Hook for additional form markers
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tx_keuserregister']['additionalMarkers'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tx_keuserregister']['additionalMarkers'] as $_classRef) {
				$_procObj = & t3lib_div::getUserObj($_classRef);
				$_procObj->additionalMarkers(&$this->markerArray,&$this,$errors);
			}
		}

		// get subpart
		if ($this->mode == 'edit') $content = $this->cObj->getSubpart($this->templateCode,'###EDIT_FORM###');
		else $content = $this->cObj->getSubpart($this->templateCode,'###REGISTRATION_FORM###');

		// substitute marker array
		$content = $this->cObj->substituteMarkerArray($content,$this->markerArray,$wrap='###|###',$uppercase=1);

		// hide username field if email is used as username
		if ($this->conf['emailIsUsername']) $content = $this->cObj->substituteSubpart ($content, '###SUB_FIELD_USERNAME###', '');

		// hide password field if edit mode is set
		if ($this->mode == 'edit') $content = $this->cObj->substituteSubpart ($content, '###SUB_FIELD_PASSWORD###', '');

		return $content;
	}

	/**
	* Renders a tooltip for one form field
	*
	* @param	text		Text to display in the tooltip
	* @return	html code for tooltip
	*/
	function renderTooltip($tooltipText) {
		if ($tooltipText && $this->tooltipAvailable) {
			return tx_fetooltip::tooltip('help.gif', $tooltipText);
		} else {
			return '';
		}
	}

	/**
	* Description
	*
	* @param	type		desc
	* @return	The content that is displayed on the website
	*/
	function renderInputField($fieldConf, $fieldName) {
		switch ($fieldConf['type']) {

			case 'text':
				$content = $this->cObj->getSubpart($this->templateCode,'###SUB_INPUT_TEXT###');
				$tempMarkerArray = array(
					'name' => $this->prefixId.'['.$fieldName.']',
					'value' => $this->piVars[$fieldName],
					'tooltip' => $this->renderTooltip($fieldConf['tooltip'])
				);
				$content = $this->cObj->substituteMarkerArray($content,$tempMarkerArray,$wrap='###|###',$uppercase=1);
				break;

			case 'textarea':
				$content = $this->cObj->getSubpart($this->templateCode,'###SUB_INPUT_TEXTAREA###');
				$tempMarkerArray = array(
					'name' => $this->prefixId.'['.$fieldName.']',
					'value' => $this->piVars[$fieldName],
					'tooltip' => $this->renderTooltip($fieldConf['tooltip'])
				);
				$content = $this->cObj->substituteMarkerArray($content,$tempMarkerArray,$wrap='###|###',$uppercase=1);
				break;

			case 'password':
				$value = $this->piVars['password'] ? $this->piVars['password'] : '';
				$valueAgain = $this->piVars['password_again'] ? $this->piVars['password_again'] : '';
				$content = $this->cObj->getSubpart($this->templateCode,'###SUB_PASSWORD###');
				$content = $this->cObj->substituteMarker($content,'###VALUE###',$value);
				$content = $this->cObj->substituteMarker($content,'###VALUE_AGAIN###',$valueAgain);
				$content = $this->cObj->substituteMarker($content,'###LABEL_PASSWORD_AGAIN###',$this->pi_getLL('label_password_again'));
				$content = $this->cObj->substituteMarker($content,'###TOOLTIP###', $this->renderTooltip($fieldConf['tooltip']));
				break;

			case 'checkbox':
				$fieldValues = explode(',',$fieldConf['values']);
				foreach ($fieldValues as $key => $value) {

					$checked = false;
					// set default value if create mode and form not sent
					if ($this->mode == 'create' && empty($this->piVars['step'])) {
						if ($value == $fieldConf['default']) $checked = true;
					}
					else $checked = $this->piVars[$fieldName] == $value ? true : false;

					$tempMarkerArray = array(
						'name' => $this->prefixId.'['.$fieldName.']',
						'value' => $value,
						'label' => $this->pi_getLL('label_'.$fieldName.'_'.$value),
						#'checked' => ($this->piVars[$fieldName] == $value) ? 'checked="checked" ' : '',
						'checked' => $checked ? 'checked="checked" ' : '',
						'tooltip' => $this->renderTooltip($fieldConf['tooltip'])
					);

					$tempContent = $this->cObj->getSubpart($this->templateCode,'###SUB_CHECKBOX_ROW###');
					$tempContent = $this->cObj->substituteMarkerArray($tempContent,$tempMarkerArray,$wrap='###|###',$uppercase=1);
					$content .= $tempContent;
				}
				break;

			case 'radio':
				$fieldValues = explode(',',$fieldConf['values']);
				foreach ($fieldValues as $key => $value) {
					$tempMarkerArray = array(
						'name' => $this->prefixId.'['.$fieldName.']',
						'value' => $value,
						'label' => $this->pi_getLL('label_'.$fieldName.'_'.$value),
						'checked' => ($this->piVars[$fieldName] == $value) ? 'checked="checked" ' : '',
						'tooltip' => $this->renderTooltip($fieldConf['tooltip'])
					);

					$tempContent = $this->cObj->getSubpart($this->templateCode,'###SUB_RADIO_ROW###');
					$tempContent = $this->cObj->substituteMarkerArray($tempContent,$tempMarkerArray,$wrap='###|###',$uppercase=1);
					$content .= $tempContent;
				}

				break;

			case 'select':
				$fieldValues = explode(',',$fieldConf['values']);
				foreach ($fieldValues as $key => $value) {
					$tempMarkerArray = array(
						'value' => $value,
						'label' => $this->pi_getLL('label_'.$fieldName.'_'.$value),
						'selected' => ($this->piVars[$fieldName] == $value) ? 'selected="selected" ' : '',
						'tooltip' => $this->renderTooltip($fieldConf['tooltip'])
					);
					$tempContent = $this->cObj->getSubpart($this->templateCode,'###SUB_SELECT_OPTION###');
					$tempContent = $this->cObj->substituteMarkerArray($tempContent,$tempMarkerArray,$wrap='###|###',$uppercase=1);
					$optionsContent .= $tempContent;
				}
				$content = $this->cObj->getSubpart($this->templateCode,'###SUB_SELECT###');
				$content = $this->cObj->substituteMarker($content,'###NAME###',$this->prefixId.'['.$fieldName.']');
				$content = $this->cObj->substituteSubpart ($content, '###SUB_SELECT_OPTION###', $optionsContent);
				break;


			case 'directmail':
				$fields = '*';
				$table = 'sys_dmail_category';
				$where = 'sys_language_uid="'.$GLOBALS['TSFE']->sys_language_uid.'" ';
				if (!empty($fieldConf['values'])) $where .= 'AND pid in("'.t3lib_div::removeXSS($fieldConf['values']).'") ';
				$where .= $this->cObj->enableFields($table);
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='');
				while ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {

					#$checked = t3lib_div::inList($this->piVars[$fieldName],$row['uid']);
					if (is_array($this->dmailValues)) {
						$checked = in_array($row['uid'],$this->dmailValues);
					}
					else $checked = false;

					$tempMarkerArray = array(
						'name' => $this->prefixId.'['.$fieldName.']['.$row['uid'].']',
						'value' => 1,
						'label' => $row['category'],
						'checked' => $checked ? 'checked="checked" ' : '',
						'tooltip' => $this->renderTooltip($fieldConf['tooltip'])
					);
					$tempContent = $this->cObj->getSubpart($this->templateCode,'###SUB_CHECKBOX_ROW###');
					$tempContent = $this->cObj->substituteMarkerArray($tempContent,$tempMarkerArray,$wrap='###|###',$uppercase=1);
					$content .= $tempContent;
				}
				break;

			case 'select_db_relation':
				/*
				 example TS -configuration:
				   myDatabaseField {
					type = select_db_relation
					pid = 3
					displayField = name
				  }
				*/
				// compile sql query for select values
				$fields = '*';
				$table = $fieldConf['table'];
				$where = '1=1';
				if ($fieldConf['pid']) $where .= ' AND pid=' . intval($fieldConf['pid']);
				$where .= $this->cObj->enableFields($table);
				//$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy=,$limit='');
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields, $table, $where, '', $fieldConf['displayField']);

				// build options
				$optionsContent = '';
				while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				   $optionsContent .= '<option value="' . $row['uid'] . '" ';
				   if ($this->piVars[$fieldName] == $row['uid']) $optionsContent .= ' selected="selected" ';
				   $optionsContent .= '>'.$row[$fieldConf['displayField']].'</option>';
				}

				// compile
				$content = $this->cObj->getSubpart($this->templateCode,'###SUB_SELECT###');
				$content = $this->cObj->substituteMarker($content,'###NAME###',$this->prefixId.'['.$fieldName.']');
				$content = $this->cObj->substituteSubpart ($content, '###SUB_SELECT_OPTION###', $optionsContent);
				$content = $this->cObj->substituteMarker($content,'###TOOLTIP###', $this->renderTooltip($fieldConf['tooltip']));
				break;

			case 'image':
				// file already uploaded
				if ($this->piVars[$fieldName] != "") {

					// generate thumbnail
					$imageConf['file.'] = $fieldConf['file.'];
					$imageConf['file'] = $this->fileUploadDir . $this->piVars[$fieldName];
					$imageConf['altText'] = $this->piVars[$fieldName];
					$thumbnail=$this->cObj->IMAGE($imageConf);

					$content = $this->cObj->getSubpart($this->templateCode,'###SUB_INPUT_IMAGE_UPLOADED###');
					$tempMarkerArray = array(
						'thumbnail' => $thumbnail,
						'filename' => $this->piVars[$fieldName],
						'fieldname' => $this->prefixId.'['.$fieldName.']',
						'name_upload_new' => $this->prefixId.'['.$fieldName.'_new]',
						'tooltip' => $this->renderTooltip($fieldConf['tooltip'])
					);
					$content = $this->cObj->substituteMarkerArray($content,$tempMarkerArray,$wrap='###|###',$uppercase=1);
				}
				// no upload done
				else {
					$content = $this->cObj->getSubpart($this->templateCode,'###SUB_INPUT_IMAGE###');
					$tempMarkerArray = array(
						'name' => $this->prefixId.'['.$fieldName.']',
						//'value' => $this->piVars[$fieldName],
						'tooltip' => $this->renderTooltip($fieldConf['tooltip'])
					);
					$content = $this->cObj->substituteMarkerArray($content,$tempMarkerArray,$wrap='###|###',$uppercase=1);
				}
				break;

			case 'country':

				// check if static tables are loaded
				// loaded
				if (t3lib_extMgm::isLoaded('static_info_tables')) {

					// check if current language extension is loaded, otherwise use english version
					$currentLang = $GLOBALS['TSFE']->tmpl->setup['config.']['language'];
					$staticInfoTableExtName = 'static_info_tables_'.$currentLang;
					$countryNameField = t3lib_extMgm::isLoaded($staticInfoTableExtName) ? 'cn_short_'.$currentLang : 'cn_short_en';

					// prefill value
					// not selected yet?
					if ($this->piVars[$fieldName]=="") {
						$fields = '*';
						$table = 'static_countries';
						$where = 'cn_iso_2="'.$currentLang.'" ';
						$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='1');
						$row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
						$prefillValue = $row[$countryNameField];
					}
					else $prefillValue = $this->piVars[$fieldName];

					// get db data
					$fields = '*';
					$table = 'static_countries';
					$where = '';
					$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy=$countryNameField,$limit='');
					// build options
					$optionsContent = '';
					while ($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
					   $optionsContent .= '<option value="'.$row[$countryNameField].'" ';
					   if ($prefillValue == $row[$countryNameField]) $optionsContent .= ' selected="selected" ';
					   $optionsContent .= '>'.$row[$countryNameField].'</option>';
					}
					$content = $this->cObj->getSubpart($this->templateCode,'###SUB_SELECT###');
					$content = $this->cObj->substituteMarker($content,'###NAME###',$this->prefixId.'['.$fieldName.']');
					$content = $this->cObj->substituteSubpart ($content, '###SUB_SELECT_OPTION###', $optionsContent);
					$content = $this->cObj->substituteMarker($content,'###TOOLTIP###', $this->renderTooltip($fieldConf['tooltip']));
				}
				// not loaded
				else {
					$content = 'static_info_tables not loaded';
				}
				break;
		}
		return $content;
	}

	/**
	* Get general markers as array
	*
	* @return	array 	general markers
	*/
	function getGeneralMarkers() {

		// generate form action
		unset($linkconf);
		$linkconf['parameter'] = $GLOBALS['TSFE']->id;
		$formAction = $this->cObj->typoLink_URL($linkconf).'#formstart';

		$generalMarkers = array(
			'clearer' => $this->cObj->getSubpart($this->templateCode,'###SUB_CLEARER###'),
			'form_name' => 'ke_userregister_registration_form',
			'form_action' => $formAction,
		);


		return $generalMarkers;
	}

	/**
	* check for german date format which is DD.MM.YYYY
	*
	* @param string $date_string
	* @return	bool
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
	* @return	bool
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
	* @return	The content that is displayed on the website
	*/
	function evaluateFormData() {

		$errors = array();

		foreach ($this->fields as $fieldName => $fieldConf) {

			$fieldName = str_replace('.','',$fieldName);

			// check if required field is empty
			if (strstr($fieldConf['eval'],'required') && empty($this->piVars[$fieldName])) {
				$errors[$fieldName] = $this->pi_getLL('error_required');
			}

			// check if field value is numeric
			if (strstr($fieldConf['eval'],'numeric') && !is_numeric($this->piVars[$fieldName])) {
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
					$where = 'username="'.t3lib_div::removeXSS($this->piVars[$fieldName]).'" AND deleted != 1';
					$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid','fe_users',$where);
					$anz = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
					if ($anz) {
						if ($this->conf['emailIsUsername']) $errors['email'] = $this->pi_getLL('error_email_existent');
						else $errors['username'] = $this->pi_getLL('error_username_existent');
					}
				}
			}

			// checks for already existent email
			// (email is used as username)
			if ($this->conf['emailIsUsername'] && $fieldName == 'email') {
				// check only if create user or user edited email value
				if ($this->mode == 'create' || ($this->mode == 'edit' && $this->emailHasChanged())) {
					if (!empty($this->piVars[$fieldName])) {
						$where = 'username="'.t3lib_div::removeXSS($this->piVars[$fieldName]).'" AND deleted != 1';
						$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid','fe_users',$where);
						$anz = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
						if ($anz) {
							if ($this->conf['emailIsUsername']) $errors['email'] = $this->pi_getLL('error_email_existent');
							else $errors['username'] = $this->pi_getLL('error_username_existent');
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
					$errors[$fieldName] = sprintf($this->pi_getLL('error_password_length'),$this->conf['password.']['minLength']);
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
						$errors[$fieldName] =  sprintf($this->pi_getLL('error_upload_filesize'), $uploadData['name'][$fieldName], $this->filesize_format($this->maxFileSize, '', ''));
						$process = false;
					}

					// file type not allowed
					$allowedFileTypes  =  t3lib_div::trimExplode(',',strtolower($this->conf['upload.']['allowedFileTypes']));
					$dotPos = strpos($uploadData['name'][$fieldName],'.');
					$fileEnding = strtolower(substr($uploadData['name'][$fieldName],$dotPos+1));
					if (!in_array($fileEnding, $allowedFileTypes)) {
						$errors[$fieldName] =  sprintf($this->pi_getLL('error_upload_filetype'), $uploadData['name'][$fieldName]);
						$process = false;
					}
				}

				// write field if OK
				if ($process) {
					$uploadedFileName = $this->handleUpload($fieldName);
					if (!empty($uploadedFileName)) $this->piVars[$fieldName] = $uploadedFileName;
					else $errors[$fieldName] = $this->pi_getLL('error_upload_no_success');
				}
			}

			// process new upload --> overwrite old file
			if ($fieldConf['type'] == 'image' && !empty($GLOBALS['_FILES'][$this->prefixId]['name'][$fieldName.'_new'])) {
				$uploadData = $GLOBALS['_FILES'][$this->prefixId];
				$process = true;
				if ($uploadData['size'][$fieldName.'_new'] > 0) {

					// file too big
					if ($uploadData['size'][$fieldName.'_new'] > $this->conf['upload.']['maxFileSize']) {
						$errors[$fieldName] =  sprintf($this->pi_getLL('error_upload_filesize'), $uploadData['name'][$fieldName.'_new'], $this->filesize_format($this->maxFileSize, '', ''));
						$process = false;
					}

					// file type not allowed
					$allowedFileTypes  =  t3lib_div::trimExplode(',',strtolower($this->conf['upload.']['allowedFileTypes']));
					$dotPos = strpos($uploadData['name'][$fieldName.'_new'],'.');
					$fileEnding = strtolower(substr($uploadData['name'][$fieldName.'_new'],$dotPos+1));
					if (!in_array($fileEnding, $allowedFileTypes)) {
						$errors[$fieldName] =  sprintf($this->pi_getLL('error_upload_filetype'), $uploadData['name'][$fieldName]);
						$process = false;
					}
				}

				// write field if OK
				if ($process) {
					$uploadedFileName = $this->handleUpload($fieldName.'_new');
					if (!empty($uploadedFileName)) $this->piVars[$fieldName] = $uploadedFileName;
					else $errors[$fieldName] = $this->pi_getLL('error_upload_no_success');
				}
			}
		}

		// Hook for further evaluations
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tx_keuserregister']['specialEvaluations'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tx_keuserregister']['specialEvaluations'] as $_classRef) {
				$_procObj = & t3lib_div::getUserObj($_classRef);
				$_procObj->processSpecialEvaluations(&$errors,$this);
			}
		}

		// if errors occured: render form with error messages
		if (sizeof($errors)) return $this->renderForm($errors);
		// otherwise: process form data
		else {
			// process edit form
			if ($this->mode == 'edit') return $this->processEditFormData();
			// process registration form
			else if ($this->mode == 'create') return $this->processRegistrationFormData();
		}
	}

	/**
	* Description
	*
	* @param	type		desc
	* @return	The content that is displayed on the website
	*/
	function processRegistrationFormData() {

		// check if storage page for records is defined
		if (!$this->conf['userDataPID']) die($this->prefixId.': ERROR: No user data pid defined');

		// check if default usergroup is set
		if (!$this->conf['defaultUsergroup']) die($this->prefixId.': ERROR: No default usergroup defined');

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
			$fieldName = str_replace('.','',$fieldName);

			// special handling for directmail fields
			if ($fieldConf['type'] == 'directmail') {
				if (sizeof($this->piVars[$fieldName])) {
					foreach ($this->piVars[$fieldName] as $catUid => $value) {
						if ($value == 1) $dmailInsertValues[] = $catUid;
					}
				}
			}
			// save all fields that are not marked as "doNotSaveInDB"
			else if (!$fieldConf['doNotSaveInDB']) $fields_values[$fieldName] = t3lib_div::removeXSS($this->piVars[$fieldName]);

		}

		// set name
		$fields_values['name'] = t3lib_div::removeXSS($this->piVars['first_name'].' '.$this->piVars['last_name']);

		// set email address as username if defined
		if ($this->conf['emailIsUsername']) $fields_values['username'] = t3lib_div::removeXSS($this->piVars['email']);
		else $fields_values['username'] = t3lib_div::removeXSS($this->piVars['username']);

		// encrypt password if defined in ts in $this->conf['password.']['encryption']
		$fields_values['password'] = $this->lib->encryptPassword(t3lib_div::removeXSS($this->piVars['password']), $this->conf['password.']['encryption']);

		// Hook for further data processing before saving to db
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tx_keuserregister']['specialDataProcessing'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tx_keuserregister']['specialDataProcessing'] as $_classRef) {
				$_procObj = & t3lib_div::getUserObj($_classRef);
				$_procObj->processSpecialDataProcessing(&$fields_values,$this);
			}
		}

		// save data to db an go on to further steps
		if ($GLOBALS['TYPO3_DB']->exec_INSERTquery($table,$fields_values,$no_quote_fields=FALSE)) {

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
					$GLOBALS['TYPO3_DB']->exec_INSERTquery($table,$fields_values,$no_quote_fields=FALSE);
				}
			}

			// Hook for further data processing after saving to db
			if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tx_keuserregister']['specialDataProcessingAfterSaveToDB'])) {
				foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tx_keuserregister']['specialDataProcessingAfterSaveToDB'] as $_classRef) {
					$_procObj = & t3lib_div::getUserObj($_classRef);
					$_procObj->processSpecialDataProcessingAfterSaveToDB(&$fields_values,$this,$feuser_uid);
				}
			}

			// generate hash and save in database
			$hash = $this->getUniqueCode();
			$table = 'tx_keuserregister_hash';
			$fields_values = array(
				'hash' => $hash,
				'feuser_uid' => $feuser_uid,
				'tstamp' => time(),
			);
			if ($GLOBALS['TYPO3_DB']->exec_INSERTquery($table,$fields_values,$no_quote_fields=FALSE)) {

				// generate html mail content
				$htmlBody = $this->cObj->getSubpart($this->templateCode,'###CONFIRMATION_REQUEST###');

				// use salutation based on users gender
				$salutationCode = $this->piVars['gender'] == 1 ? 'female' : 'male';

				// generate confirmation link
				unset($linkconf);
				$linkconf['parameter'] = $GLOBALS['TSFE']->id;
				$linkconf['additionalParams'] = '&confirm='.$hash;
				$confirmLinkUrl = t3lib_div::getIndpEnv('TYPO3_SITE_URL').$this->cObj->typoLink_URL($linkconf);
				$confirmationLink = '<a href="'.$confirmLinkUrl.'">'.$confirmLinkUrl.'</a>';

				// generate decline link
				unset($linkconf);
				$linkconf['parameter'] = $GLOBALS['TSFE']->id;
				$linkconf['additionalParams'] = '&decline='.$hash;
				$declineLinkUrl = t3lib_div::getIndpEnv('TYPO3_SITE_URL').$this->cObj->typoLink_URL($linkconf);
				$declineLink = '<a href="'.$declineLinkUrl.'">'.$declineLinkUrl.'</a>';

				$markerArray = array(
					'salutation' => $this->pi_getLL('salutation_'.$salutationCode),
					'first_name' => $this->piVars['first_name'],
					'last_name' => $this->piVars['last_name'],
					'confirmation_request_text' => sprintf($this->pi_getLL('confirmation_request_text'),t3lib_div::getIndpEnv('TYPO3_SITE_URL')),
					'confirmation_link' => $confirmationLink,
					'decline_text' => $this->pi_getLL('decline_text'),
					'decline_link' => $declineLink,
					'farewell_text' => $this->pi_getLL('farewell_text'),
					'site_url' => t3lib_div::getIndpEnv('TYPO3_SITE_URL'),
				);
				$htmlBody = $this->cObj->substituteMarkerArray($htmlBody,$markerArray,$wrap='###|###',$uppercase=1);

				// send double-opt-in-mail
				$subject = $this->pi_getLL('confirmation_request_subject');
				$this->sendNotificationEmail($this->piVars['email'], $subject, $htmlBody);

				// print message
				$content = $this->cObj->getSubpart($this->templateCode,'###FORM_SUCCESS_MESSAGE###');
				$markerArray = array(
					'headline' => $this->pi_getLL('form_success_headline'),
					'salutation' => $this->pi_getLL('salutation_'.$salutationCode),
					'first_name' => $this->piVars['first_name'],
					'last_name' => $this->piVars['last_name'],
					'form_success_text' => $this->pi_getLL('form_success_text'),
				);
				$content = $this->cObj->substituteMarkerArray($content,$markerArray,$wrap='###|###',$uppercase=1);
				return $content;

			}
			else die($this->prefixId.': ERROR: DATABASE ERROR WHEN SAVING RECORD');
		}
		else die($this->prefixId.': ERROR: DATABASE ERROR WHEN SAVING RECORD');
	}

	/**
	* Description
	*
	* @param	type		desc
	* @return	The content that is displayed on the website
	*/
	function processEditFormData() {

		// update fe user record
		$table = 'fe_users';
		$where = 'uid="'.intval($GLOBALS['TSFE']->fe_user->user['uid']).'" ';

		foreach ($this->fields as $fieldName => $fieldConf) {

			$fieldName = str_replace('.','',$fieldName);

			// special handling for directmail fields
			if ($fieldConf['type'] == 'directmail') {
				// delete all mm entries in db
				$GLOBALS['TYPO3_DB']->exec_DELETEquery('sys_dmail_feuser_category_mm','uid_local="'.$GLOBALS['TSFE']->fe_user->user['uid'].'"');
				if (sizeof($this->piVars[$fieldName])) {
					foreach ($this->piVars[$fieldName] as $catUid => $value) {
						if ($value == 1) $dmailInsertValues[] = $catUid;
					}
				}
			}
			// save all fields that are not marked as "doNotSaveInDB"
			else if (!$fieldConf['doNotSaveInDB']) $fields_values[$fieldName] = t3lib_div::removeXSS($this->piVars[$fieldName]);

		}

		// Hook for further data processing before saving to db
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tx_keuserregister']['specialDataProcessing'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['tx_keuserregister']['specialDataProcessing'] as $_classRef) {
				$_procObj = &t3lib_div::getUserObj($_classRef);
				$_procObj->processSpecialDataProcessing(&$fields_values,$this);
			}
		}

		// do not process email change here
		if ($this->mode == 'edit') unset($fields_values['email']);

		if ($GLOBALS['TYPO3_DB']->exec_UPDATEquery($table,$where,$fields_values,$no_quote_fields=FALSE)) {

			// process directmail values
			if (is_array($dmailInsertValues)) {
				foreach ($dmailInsertValues as $key => $catUid) {
					$table = 'sys_dmail_feuser_category_mm';
					$fields_values = array(
					    'uid_local' => $GLOBALS['TSFE']->fe_user->user['uid'],
					    'uid_foreign' => $catUid,
					);
					$GLOBALS['TYPO3_DB']->exec_INSERTquery($table,$fields_values,$no_quote_fields=FALSE);
				}
			}


			$content = $this->cObj->getSubpart($this->templateCode,'###SUB_MESSAGE###');
			$content = $this->cObj->substituteMarker($content,'###HEADLINE###',$this->pi_getLL('edit_success_headline'));
			#$content = $this->cObj->substituteMarker($content,'###MESSAGE###',$this->pi_getLL('edit_success_text'));

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
					'new_email' => t3lib_div::removeXSS($this->piVars['email']),
				);
				$GLOBALS['TYPO3_DB']->exec_INSERTquery($hashTable,$hashFieldsValues);

				// send email confirmation request to user's new email address
				$this->sendEmailChangeConfirmationRequestMail();

				// print success message
				$content = $this->cObj->substituteMarker($content,'###MESSAGE###',$this->pi_getLL('edit_sucess_text_email_change'));
			}
			else $content = $this->cObj->substituteMarker($content,'###MESSAGE###',$this->pi_getLL('edit_success_text'));

			return $content;

		}
		else die($this->prefixId.': ERROR: DB error when saving data');

	}



	/**
	* Send the confirmation request mail for changing
	* the user's email address
	*
	* @param	type		desc
	* @return	The content that is displayed on the website
	*/
	function sendEmailChangeConfirmationRequestMail() {
		// generate html mail content
		$htmlBody = $this->cObj->getSubpart($this->templateCode,'###CONFIRMATION_REQUEST###');

		// use salutation based on users gender
		$salutationCode = $this->piVars['gender'] == 1 ? 'female' : 'male';

		// generate confirmation link
		unset($linkconf);
		$linkconf['parameter'] = $GLOBALS['TSFE']->id;
		$linkconf['additionalParams'] = '&mailconfirm='.$this->emailChangeHash;
		$confirmLinkUrl = t3lib_div::getIndpEnv('TYPO3_SITE_URL').$this->cObj->typoLink_URL($linkconf);
		$confirmationLink = '<a href="'.$confirmLinkUrl.'">'.$confirmLinkUrl.'</a>';

		// generate decline link
		unset($linkconf);
		$linkconf['parameter'] = $GLOBALS['TSFE']->id;
		$linkconf['additionalParams'] = '&maildecline='.$this->emailChangeHash;
		$declineLinkUrl = t3lib_div::getIndpEnv('TYPO3_SITE_URL').$this->cObj->typoLink_URL($linkconf);
		$declineLink = '<a href="'.$declineLinkUrl.'">'.$declineLinkUrl.'</a>';

		$markerArray = array(
			'salutation' => $this->pi_getLL('salutation_'.$salutationCode),
			'first_name' => $this->piVars['first_name'],
			'last_name' => $this->piVars['last_name'],
			'confirmation_request_text' => sprintf($this->pi_getLL('mail_confirmation_request_text'),t3lib_div::getIndpEnv('TYPO3_SITE_URL')),
			'confirmation_link' => $confirmationLink,
			'decline_text' => $this->pi_getLL('mail_decline_text'),
			'decline_link' => $declineLink,
			'farewell_text' => $this->pi_getLL('farewell_text'),
			'site_url' => t3lib_div::getIndpEnv('TYPO3_SITE_URL'),
		);
		$htmlBody = $this->cObj->substituteMarkerArray($htmlBody,$markerArray,$wrap='###|###',$uppercase=1);

		// send double-opt-in-mail
		$subject = $this->pi_getLL('mail_confirmation_request_subject');
		$this->sendNotificationEmail($this->piVars['email'], $subject, $htmlBody);
	}




	/**
	* Description
	*
	* @param	type		desc
	* @return	The content that is displayed on the website
	*/
	function emailHasChanged() {
		$fields = '*';
		$table = 'fe_users';
		$where = 'uid="'.intval($GLOBALS['TSFE']->fe_user->user['uid']).'" ';
		$where .= $this->cObj->enableFields($table);
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='1');
		$anz = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
		$userRow=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		if ($userRow['email'] != t3lib_div::removeXSS($this->piVars['email'])) return true;
		else return false;
	}



	/**
	* Description
	*
	* @param	type		desc
	* @return	The content that is displayed on the website
	*/
	function getUniqueCode($length=8) {
		$code = md5(uniqid(rand(), true));
		if ($length != "") $codeString = substr($code, 0, $length);
		else $codeString = $code;

		// check if hash already existent in db
		$fields = '*';
		$table = 'tx_keuserregister_hash';
		$where = 'hash="'.$codeString.'"';
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='');
		$anz = $GLOBALS['TYPO3_DB']->sql_num_rows($res);
		if (!$anz) return $codeString;
		else return $this->getUniqueCode();

	}


	/**
	* Check if value is an unsigned int
	*
	* @param	mixed	value that has to be checked
	* @return	bool
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

		// create the plain message body
		$message = html_entity_decode(strip_tags($html_body), ENT_QUOTES, $GLOBALS['TSFE']->renderCharset);

		// inspired by code from tt_products, thanks
		$Typo3_htmlmail = t3lib_div::makeInstance('t3lib_htmlmail');
		$Typo3_htmlmail->start();

		$Typo3_htmlmail->subject = $subject;
		$Typo3_htmlmail->from_email = $this->conf['notification.']['from_email'];
		$Typo3_htmlmail->from_name = $this->conf['notification.']['from_name'];
		$Typo3_htmlmail->replyto_email = $Typo3_htmlmail->from_email;
		$Typo3_htmlmail->replyto_name = $Typo3_htmlmail->from_name;
		$Typo3_htmlmail->organisation = '';

		if ($sendAsHTML)  {
			$Typo3_htmlmail->theParts['html']['content'] = $html_body;
			$Typo3_htmlmail->theParts['html']['path'] = t3lib_div::getIndpEnv('TYPO3_REQUEST_HOST') . '/';

			$Typo3_htmlmail->extractMediaLinks();
			$Typo3_htmlmail->extractHyperLinks();
			$Typo3_htmlmail->fetchHTMLMedia();
			$Typo3_htmlmail->substMediaNamesInHTML(0);	// 0 = relative
			$Typo3_htmlmail->substHREFsInHTML();
			$Typo3_htmlmail->setHTML($Typo3_htmlmail->encodeMsg($Typo3_htmlmail->theParts['html']['content']));
			if ($message)	{
				$Typo3_htmlmail->addPlain($message);
			}
		} else {
			$Typo3_htmlmail->addPlain($message);
		}
		$Typo3_htmlmail->setHeaders();
		$Typo3_htmlmail->setContent();
		$Typo3_htmlmail->setRecipient(explode(',', $toEMail));
		$Typo3_htmlmail->sendTheMail();
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

		if($success && move_uploaded_file($GLOBALS['_FILES'][$this->prefixId]['tmp_name'][$fieldName], $uploadfile)) {
			// change rights so that everyone can read the file
			chmod($uploadfile,octdec('0744'));
 		} else {
			$error = $this->pi_getLL('error_file_upload_not_successful','Error: File upload was not successfull.');
			$success=false;
		}

		if ($success) return basename($uploadfile);
		else return '';
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
		if (strlen($format) == 0) $format = $defaultFormat;
		$bytes = max(0, (int) $bytes);
		$units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
		$power = array_search($force, $units);
		if ($power === false) $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
		return sprintf($format, $bytes / pow(1024, $power), $units[$power]);
	}

}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_userregister/pi1/class.tx_keuserregister_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_userregister/pi1/class.tx_keuserregister_pi1.php']);
}

?>
