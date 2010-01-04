<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009 Christian Buelter <buelter@kennziffer.com>
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
 * Set info in page module
 *
 * @author	Christian Bülter
 * @package	TYPO3
 * @subpackage	tx_keuserregister
 */
class tx_keuserregister_cms_layout {
	/**
	 * Returns information about this extension's pi1 plugin
	 *
	 * @param	array		$params	Parameters to the hook
	 * @param	object		$pObj	A reference to calling object
	 * @return	string		Information about pi1 plugin
	 */
	function getExtensionSummary($params, &$pObj) {
		if ($params['row']['list_type'] == 'ke_userregister_pi1') {
			$data = t3lib_div::xml2array($params['row']['pi_flexform']);

			if (is_array($data)) {
				$key = strtolower($data['data']['MAIN']['lDEF']['mode']['vDEF']);
				$result = $GLOBALS['LANG']->sL('LLL:EXT:ke_userregister/pi1/locallang.xml:ff.mode')
						. ': ' . $GLOBALS['LANG']->sL('LLL:EXT:ke_userregister/pi1/locallang.xml:ff.mode.I.' . $key);
			}
			if (!$result) {
				$result = $GLOBALS['LANG']->sL('LLL:EXT:ke_userregister/pi1/locallang.xml:ff.mode_not_defined');
			}

		}

		return $result;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_userregister/lib/class.tx_keuserregister_cms_layout.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/ke_userregister/lib/class.tx_keuserregister_cms_layout.php']);
}
?>
