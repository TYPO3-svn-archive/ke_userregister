<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010 Christian Buelter <buelter@kennziffer.com>
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
 * Helper functions for ke_userregister
 *
 * @author	Christian Bülter
 * @package	TYPO3
 * @subpackage	tx_keuserregister
 */
class tx_keuserregister_lib {
	var $prefixId      = 'tx_keuserregister_lib';
	var $scriptRelPath = 'lib/class.tx_keuserregister_lib.php';
	var $extKey        = 'ke_userregister';	

	/**
 	* encrypts a given plaintext password with the method defined in ts
 	*
 	* @param   string plaintext passord
 	* @return  string encrypted password
 	* @author  Christian Buelter <buelter@kennziffer.com>
 	* @since   Wed Feb 24 2010 11:41:05 GMT+0100
 	*/
	public function encryptPassword($plain_password='', $encryption_method='') {
		switch ($encryption_method) {
			case 'md5':
				$encrypted_password = md5($plain_password);
				break;
			case 'salted':
				if (t3lib_extMgm::isLoaded('saltedpasswords')) {
					if (tx_saltedpasswords_div::isUsageEnabled('FE')) {
						$objSalt = tx_saltedpasswords_salts_factory::getSaltingInstance(NULL);
						if (is_object($objSalt)) {
							$encrypted_password = $objSalt->getHashedPassword($plain_password);
						} else {
							die($this->prefixId.': ERROR: Initialization of saltedpasswords failed.');
						}
					} else {
						die($this->prefixId.': ERROR: Salted passwords are not enabled in extension saltedpasswords.');
					}
				} else {
					die($this->prefixId.': ERROR: Extension saltedpasswords is not available.');
				}
				break;
			case 'none':
				$encrypted_password = $plain_password;
				break;
			default:
				die($this->prefixId.': ERROR: No password encryption method set.');
		}
		return $encrypted_password;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_userregister/lib/class.tx_keuserregister_cms_layout.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_userregister/lib/class.tx_keuserregister_cms_layout.php']);
}
?>
