<?php
if (!defined ('TYPO3_MODE')) {
 	die ('Access denied.');
}

t3lib_extMgm::addPItoST43($_EXTKEY, 'pi1/class.tx_keuserregister_pi1.php', '_pi1', 'list_type', 0);
t3lib_extMgm::addPItoST43($_EXTKEY, 'pi2/class.tx_keuserregister_pi2.php', '_pi2', 'list_type', 0);
t3lib_extMgm::addPItoST43($_EXTKEY, 'pi3/class.tx_keuserregister_pi3.php', '_pi3', 'list_type', 0);

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['cms/layout/class.tx_cms_layout.php']['list_type_Info']['ke_userregister_pi1']['ke_userregister'] = 'EXT:ke_userregister/lib/class.tx_keuserregister_cms_layout.php:tx_keuserregister_cms_layout->getExtensionSummary';
?>
