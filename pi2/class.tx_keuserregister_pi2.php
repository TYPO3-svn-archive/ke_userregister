<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009-2014 Andreas Kiefer <kiefer@kennziffer.com>
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

require_once(t3lib_extMgm::extPath('ke_userregister', 'lib/class.tx_keuserregister_lib.php'));

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\DebugUtility;

/**
 * Plugin 'Change Password' for the 'ke_userregister' extension.
 *
 * @author	Andreas Kiefer <kiefer@kennziffer.com>
 * @package	TYPO3
 * @subpackage	tx_keuserregister
 */
class tx_keuserregister_pi2 extends tslib_pibase {
	var $prefixId      = 'tx_keuserregister_pi2';		// Same as class name
	var $scriptRelPath = 'pi2/class.tx_keuserregister_pi2.php';	// Path to this script relative to the extension dir.
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
		
		$this->piBase = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\Plugin\\AbstractPlugin');
		
		// get general extension setup
		$this->conf = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_keuserregister.'];

		// init lib
		$this->lib = GeneralUtility::makeInstance('tx_keuserregister_lib');

		// get html template
		$this->templateCode = $this->cObj->fileResource($this->conf['templateFile']);

		// include css	
		$cssFile = $GLOBALS['TSFE']->tmpl->getFileName($this->conf['cssFile']);
		if(!empty($cssFile)) {
			if (GeneralUtility::compat_version('6.0')) $GLOBALS['TSFE']->getPageRenderer()->addCssFile($cssFile);
			else $GLOBALS['TSFE']->additionalHeaderData[$this->prefixId.'_css'] = '<link rel="stylesheet" type="text/css" href="'.$cssFile.'" />';
		}

		// include jQuery
		if ($this->conf['includeJQuery']) {
			$GLOBALS['TSFE']->getPageRenderer()->addJsLibrary('jQuery', $this->conf['jQuerySource']);
		}
		
		// include js for password meter
		if ($this->conf['usePasswordStrengthMeter']) {
			
			// add password settings to JS
			$jsPwdSettings = 'var minLength='.$this->conf['password.']['minLength'].';';
			$jsPwdSettings .= 'var minNumeric='.$this->conf['password.']['minNumeric'].';';
			$jsPwdSettings .= 'var lowerChars='.$this->conf['password.']['lowerChars'].';';
			$jsPwdSettings .= 'var upperChars='.$this->conf['password.']['upperChars'].';';
			$GLOBALS['TSFE']->getPageRenderer()->addJsInlineCode('keuserregister_pwdsettings', $jsPwdSettings, TRUE, TRUE);
			
			// add complexify script
			$complexifyJSFile = $GLOBALS['TSFE']->tmpl->getFileName($this->conf['complexifyJsFile']);
			$GLOBALS['TSFE']->getPageRenderer()->addJsFile($complexifyJSFile);
			
		}
		
		// check login
		if (!$GLOBALS['TSFE']->loginUser) {
			$content = $this->cObj->getSubpart($this->templateCode,'###SUB_MESSAGE###');
			$content = $this->cObj->substituteMarker($content,'###HEADLINE###',$this->pi_getLL('no_login_headline'));
			$content = $this->cObj->substituteMarker($content,'###MESSAGE###',sprintf($this->pi_getLL('no_login_message'),$GLOBALS['TSFE']->fe_user->user['username']));
		}
		// render the form / evaluate the form
		else $content = $this->piVars['step'] == 'evaluate' ? $this->evaluateFormData() : $this->renderForm();

		return $this->pi_wrapInBaseClass($content);
	}


	/**
	* Description
	*
	* @param	type		desc
	* @return	The content that is displayed on the website
	*/
	function renderForm($errors=array()) {

		// generate form action
		unset($linkconf);
		$linkconf['parameter'] = $GLOBALS['TSFE']->id;
		$formAction = $this->cObj->typoLink_URL($linkconf).'#formstart';

		$content = $this->cObj->getSubpart($this->templateCode,'###EDIT_PASSWORD_FORM###');
		$markerArray = array (
			'form_action' => $formAction,
			'form_name' => 'ke_userregister_change_pwd',
			'label_old_password' => $this->pi_getLL('label_old_password'),
			'input_old_password' => $this->renderInputField('old_password'),
			'error_old_password' => '',
			'label_new_password' => $this->pi_getLL('label_new_password'),
			'label_password_again' => $this->pi_getLL('label_password_again'),
			'input_new_password' => $this->renderInputField('new_password'),
			'error_new_password' => '',
			'clearer' => $this->cObj->getSubpart($this->templateCode,'###SUB_CLEARER###'),
		);

		// set error markers if errors occured
		
		if ($errors['old_password']) $markerArray['error_old_password'] = $errors['old_password'];
		if ($errors['new_password']) $markerArray['error_new_password'] = $errors['new_password'];


		$content = $this->cObj->substituteMarkerArray($content,$markerArray,$wrap='###|###',$uppercase=1);

		return $content;
	}


	/**
	* Description
	*
	* @param	type		desc
	* @return	The content that is displayed on the website
	*/
	function evaluateFormData() {
		$errors = array();

		// check if old password is correct
		$fields = 'password';
		$table = 'fe_users';
		$where = 'uid="'.intval($GLOBALS['TSFE']->fe_user->user['uid']).'" ';
		$where .= $this->cObj->enableFields($table);
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($fields,$table,$where,$groupBy='',$orderBy='',$limit='1');
		$row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);

		// check salted
		if ($this->conf['password.']['encryption'] == 'salted') {
			if (t3lib_extMgm::isLoaded('saltedpasswords')) {
				if (tx_saltedpasswords_div::isUsageEnabled('FE')) {
					$objSalt = tx_saltedpasswords_salts_factory::getSaltingInstance($row['password']);
					if (is_object($objSalt)) {
						$success = $objSalt->checkPassword($this->piVars['old_password'], $row['password']);
					} else {
						die($this->prefixId.': ERROR: Initialization of saltedpasswords failed.');
					}
				} else {
					die($this->prefixId.': ERROR: Salted passwords are not enabled in extension saltedpasswords.');
				}
			} else {
				die($this->prefixId.': ERROR: Extension saltedpasswords is not available.');
			}

			if (!$success) {
				$errors['old_password'] = $this->pi_getLL('error_old_password');
			}
		}
		// check plaintext
		else if ($this->conf['password.']['encryption'] == 'none' ) {
			if ($this->lib->removeXSS($this->piVars['old_password']) !== $row['password']) {
				$errors['old_password'] = $this->pi_getLL('error_old_password');
			}
		}
		// check md5
		else if ($this->conf['password.']['encryption'] == 'md5' ) {
			if (md5($this->lib->removeXSS($this->piVars['old_password'])) !== $row['password']) {
				$errors['old_password'] = $this->pi_getLL('error_old_password');
			}
		}

		// check new password
		// encrypt password if defined in ts in $this->conf['password.']['encryption']
		// obsolete: $newPasswordInput = $this->conf['password.']['useMd5'] ? md5($this->lib->removeXSS($this->piVars['new_password'])) : $this->lib->removeXSS($this->piVars['new_password']);
		$newPasswordInput = $this->lib->encryptPassword($this->lib->removeXSS($this->piVars['new_password']), $this->conf['password.']['encryption']);

		// both passwords not the same
		if ($this->lib->removeXSS($this->piVars['new_password']) !== $this->lib->removeXSS($this->piVars['new_password_again'])) {
			$errors['new_password'] = $this->pi_getLL('error_new_password');
		}
		// check password length
		else if (strlen($this->piVars['new_password']) < $this->conf['password.']['minLength']) {
			$errors['new_password'] = sprintf($this->pi_getLL('error_new_password_length'),$this->conf['password.']['minLength']);
		}
		// new password same as old password
		else if ($this->piVars['new_password'] === $this->piVars['old_password']) {
			$errors['new_password'] = $this->pi_getLL('error_new_password_same_as_old');
		}
        else {
			
			if (!sizeof($errors['new_password']) && $this->conf['password.']['minNumeric'] > 0) {
				// check if password contains enough numeric chars
				$temp_check = str_split($this->piVars['new_password']);
				$temp_nums = 0;
				foreach ($temp_check as $check_num){
					if (is_numeric($check_num)){
						$temp_nums ++;
					}
				}
				if ($temp_nums < $this->conf['password.']['minNumeric']){
					$errors['new_password'] = sprintf($this->pi_getLL('error_new_password_numerics'),$this->conf['password.']['minNumeric']);
				}
			} 
			if (!sizeof($errors['new_password']) && $this->conf['password.']['lowerChars'] > 0) {
				// check if password contains lower characters
				$tempCheck = str_split($this->piVars['new_password']);
				$tempLower = 0; 
				foreach ($tempCheck as $checkLower) {
					if (ctype_lower($checkLower)) {
						$tempLower++;
					}
				}
				if ($tempLower == 0) {
					$errors['new_password'] = sprintf($this->pi_getLL('error_new_password_lower'), $this->conf['password.']['lowerChars']);
				}
			} 
			if (!sizeof($errors['new_password']) && $this->conf['password.']['upperChars'] > 0) {
				// check if password contains upper characters
				$tempCheck = str_split($this->piVars['new_password']);
				$tempUpper = 0; 
				foreach ($tempCheck as $checkUpper) {
					if (ctype_upper($checkUpper)) {
						$tempUpper++;
					}
				}
				if ($tempUpper == 0) {
					$errors['new_password'] = sprintf($this->pi_getLL('error_new_password_upper'), $this->conf['password.']['upperChars']);
				}
			}
		}
		
		

		// if errors occured: show form again
		if (sizeof($errors)) {
			$content = $this->renderForm($errors);
			return $content;
		}
		// save new password in db
		else {
			$table = 'fe_users';
			$where = 'uid='.intval($GLOBALS['TSFE']->fe_user->user['uid']);
			$fields_values = array(
				'password' => $this->lib->encryptPassword($this->lib->removeXSS($this->piVars['new_password']), $this->conf['password.']['encryption']),
				'tstamp' => time(),
			);

			if ($GLOBALS['TYPO3_DB']->exec_UPDATEquery($table,$where,$fields_values,$no_quote_fields=FALSE)) {
				$content = $this->cObj->getSubpart($this->templateCode,'###SUB_MESSAGE###');
				$content = $this->cObj->substituteMarker($content,'###HEADLINE###',$this->pi_getLL('success_password_change_headline'));
				$content = $this->cObj->substituteMarker($content,'###MESSAGE###',$this->pi_getLL('success_password_change_text'));
				return $content;
			}
			else die($this->prefixId.': ERROR: DB error when saving new password');
		}

	}

	static function encryptPassword($plain_password='') {
	}


	/**
	* Description
	*
	* @param	type		desc
	* @return	The content that is displayed on the website
	*/
	function renderInputField($fieldName) {

		switch ($fieldName) {

			case 'old_password':
				$value = $this->piVars['old_password'] ? $this->lib->removeXSS($this->piVars['old_password']) : '';
				$content = $this->cObj->getSubpart($this->templateCode,'###SUB_OLD_PASSWORD###');
				$content = $this->cObj->substituteMarker($content,'###VALUE_OLD_PASSWORD###',$value);
				break;

			case 'new_password':
				$value = $this->piVars['new_password'] ? $this->lib->removeXSS($this->piVars['new_password']) : '';
				$valueAgain = $this->piVars['new_password_again'] ? $this->lib->removeXSS($this->piVars['new_password_again']) : '';
				$content = $this->cObj->getSubpart($this->templateCode,'###SUB_NEW_PASSWORD###');
				$content = $this->cObj->substituteMarker($content,'###VALUE_NEW_PASSWORD###',$value);
				$content = $this->cObj->substituteMarker($content,'###VALUE_NEW_PASSWORD_AGAIN###',$valueAgain);
				$content = $this->cObj->substituteMarker($content, '###LABEL_PASSWORD_AGAIN###', $this->pi_getLL('label_password_again'));
				
				// include password strength meter
				if ($this->conf['usePasswordStrengthMeter']) {
					$passwordMeterContent = $this->cObj->getSubpart($this->templateCode, '###SUB_PASSWORD_STRENGTH_METER###');
					$passwordMeterContentText = sprintf($this->pi_getLL('password_meter_info'), $this->conf['password.']['minLength'], $this->conf['password.']['minNumeric']);
					
					if ($this->conf['password.']['lowerChars'] && $this->conf['password.']['upperChars']) {
						$passwordMeterContentText .= ' '.$this->pi_getLL('password_meter_info_lower_upper');
					} else if ($this->conf['password.']['lowerChars']) {
						$passwordMeterContentText .= ' '.$this->pi_getLL('password_meter_info_lower');
					} else if ($this->conf['password.']['upperChars']) {
						$passwordMeterContentText .= ' '.$this->pi_getLL('password_meter_info_upper');
					}
					$passwordMeterContentText .= ' '.$this->pi_getLL('password_meter_info_special');
					$passwordMeterContent = $this->cObj->substituteMarker($passwordMeterContent, '###PASSWORD_METER_INFO###', $passwordMeterContentText);
				} else {
					$passwordMeterContent = '';
				}
				$content = $this->cObj->substituteMarker($content, '###PASSWORD_STRENGTH_METER###', $passwordMeterContent);
				
				break;

		}

		return $content;
	}

}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_userregister/pi2/class.tx_keuserregister_pi2.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_userregister/pi2/class.tx_keuserregister_pi2.php']);
}

?>
