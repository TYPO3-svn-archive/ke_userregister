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


/**
 * Plugin 'Delete Profile' for the 'ke_userregister' extension.
 *
 * @author	Andreas Kiefer <kiefer@kennziffer.com>
 * @package	TYPO3
 * @subpackage	tx_keuserregister
 */
class tx_keuserregister_pi3 extends tslib_pibase {
	var $prefixId      = 'tx_keuserregister_pi3';		// Same as class name
	var $scriptRelPath = 'pi3/class.tx_keuserregister_pi3.php';	// Path to this script relative to the extension dir.
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

		// get general extension setup
		$this->conf = $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_keuserregister.'];

		// get html template
		$this->templateCode = $this->cObj->fileResource($this->conf['templateFile']);

		// include css
		if ($this->conf['cssFile']) {
			$GLOBALS['TSFE']->additionalHeaderData[$this->prefixId] .= '<link rel="stylesheet" type="text/css" href="'.$this->conf['cssFile'].'" />';
		}

		// check login
		if (!$GLOBALS['TSFE']->loginUser) {
			$content = $this->cObj->getSubpart($this->templateCode,'###SUB_MESSAGE###');
			$content = $this->cObj->substituteMarker($content,'###HEADLINE###',$this->pi_getLL('no_login_headline'));
			$content = $this->cObj->substituteMarker($content,'###MESSAGE###',sprintf($this->pi_getLL('no_login_message'),$GLOBALS['TSFE']->fe_user->user['username']));
		}
		// check if user is in group that is not allowed
		// to delete their account
		else {
			$disallowedGroups = explode(',', $this->conf['disallowDeletion']);
			$disallowed = false;
			if (sizeof($disallowedGroups)) {
				foreach ($disallowedGroups as $key => $groupId) {
					if (t3lib_div::inList($GLOBALS['TSFE']->fe_user->user['usergroup'], $groupId)) $disallowed = true;
				}
			}

			// if deletion is disallowed: print message
			if ($disallowed) {
				$content = $this->cObj->getSubpart($this->templateCode,'###SUB_MESSAGE###');
				$content = $this->cObj->substituteMarker($content,'###HEADLINE###',$this->pi_getLL('not_allowed_headline'));
				$content = $this->cObj->substituteMarker($content,'###MESSAGE###',sprintf($this->pi_getLL('not_allowed_message')));
			}
			// ask for confirmation or process deletion
			else $content = $this->piVars['delete'] ? $this->processDeletion() : $this->askForConfirmation();

		}


		return $this->pi_wrapInBaseClass($content);
	}


	/**
	* Description
	*
	* @param	type		desc
	* @return	The content that is displayed on the website
	*/
	function askForConfirmation() {

		// generate confirmation link
		unset($linkconf);
		$linkconf['parameter'] = $GLOBALS['TSFE']->id;
		$linkconf['additionalParams'] = '&'.$this->prefixId.'[delete]='.$GLOBALS['TSFE']->fe_user->user['uid'];
		$confirmLink =$this->cObj->typoLink($this->pi_getLL('delete_profile_yes'),$linkconf);

		$content = $this->cObj->getSubpart($this->templateCode,'###DELETION_CONFIRMATION###');
		$markerArray = array(
			'text' => $this->pi_getLL('delete_profile_text'),
			'question' => sprintf($this->pi_getLL('delete_profile_question'),$GLOBALS['TSFE']->fe_user->user['username']),
			'confirm_link' => $confirmLink,
		);
		$content = $this->cObj->substituteMarkerArray($content,$markerArray,$wrap='###|###',$uppercase=1);

		return $content;
	}


	/**
	* Description
	*
	* @param	type		desc
	* @return	The content that is displayed on the website
	*/
	function processDeletion() {

		// check if user is the one that should be deleted
		// otherwise: error
		if (t3lib_div::removeXSS($this->piVars['delete']) != $GLOBALS['TSFE']->fe_user->user['uid']) {
			$content = $this->cObj->getSubpart($this->templateCode,'###SUB_MESSAGE###');
			$content = $this->cObj->substituteMarker($content,'###HEADLINE###',$this->pi_getLL('error_delete_profile_authorization_headline'));
			$content = $this->cObj->substituteMarker($content,'###MESSAGE###',$this->pi_getLL('error_delete_profile_authorization_message'));
			return $content;
		}
		// logged in user is the one whose account has to be deleted
		else {
			$table = 'fe_users';
			$where = 'uid="'.t3lib_div::removeXSS($this->piVars['delete']).'" ';
			if ($GLOBALS['TYPO3_DB']->exec_DELETEquery($table,$where)) {
				$content = $this->cObj->getSubpart($this->templateCode,'###SUB_MESSAGE###');
				$content = $this->cObj->substituteMarker($content,'###HEADLINE###',$this->pi_getLL('delete_profile_success_headline'));
				$content = $this->cObj->substituteMarker($content,'###MESSAGE###',$this->pi_getLL('delete_profile_success_message'));
				return $content;
			}
		}

	}

}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_userregister/pi3/class.tx_keuserregister_pi3.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_userregister/pi3/class.tx_keuserregister_pi3.php']);
}

?>