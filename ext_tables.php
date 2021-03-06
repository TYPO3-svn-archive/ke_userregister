<?php
if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}

// add birthday field functions
require_once(t3lib_extMgm::extPath($_EXTKEY) . 'birthdayFields.php.inc');

t3lib_div::loadTCA('tt_content');
$TCA['tt_content']['types']['list']['subtypes_excludelist'][$_EXTKEY.'_pi1']='layout,select_key';

t3lib_extMgm::addPlugin(array(
	'LLL:EXT:ke_userregister/locallang_db.xml:tt_content.list_type_pi1',
	$_EXTKEY . '_pi1',
	t3lib_extMgm::extRelPath($_EXTKEY) . 'pi1/pi_icon.gif'
),'list_type');

t3lib_div::loadTCA('tt_content');
$TCA['tt_content']['types']['list']['subtypes_excludelist'][$_EXTKEY.'_pi2']='layout,select_key';

t3lib_extMgm::addPlugin(array(
	'LLL:EXT:ke_userregister/locallang_db.xml:tt_content.list_type_pi2',
	$_EXTKEY . '_pi2',
	t3lib_extMgm::extRelPath($_EXTKEY) . 'pi2/pi_icon.gif'
),'list_type');

t3lib_div::loadTCA('tt_content');
$TCA['tt_content']['types']['list']['subtypes_excludelist'][$_EXTKEY.'_pi3']='layout,select_key';

t3lib_extMgm::addPlugin(array(
	'LLL:EXT:ke_userregister/locallang_db.xml:tt_content.list_type_pi3',
	$_EXTKEY . '_pi3',
	t3lib_extMgm::extRelPath($_EXTKEY) . 'pi3/pi_icon.gif'
),'list_type');

t3lib_extMgm::addStaticFile($_EXTKEY,'static/ts/', 'KE Userregister');

// Show FlexForm field in plugin configuration
$TCA['tt_content']['types']['list']['subtypes_addlist'][$_EXTKEY.'_pi1']='pi_flexform';

// Configure FlexForm field
t3lib_extMgm::addPiFlexFormValue($_EXTKEY.'_pi1','FILE:EXT:ke_userregister/flexform_ds.xml');

// Hide not used plugin options
$TCA['tt_content']['types']['list']['subtypes_excludelist'][$_EXTKEY.'_pi1'] = 'layout,select_key,pages,recursive';
$TCA['tt_content']['types']['list']['subtypes_excludelist'][$_EXTKEY.'_pi2'] = 'layout,select_key,pages,recursive';
$TCA['tt_content']['types']['list']['subtypes_excludelist'][$_EXTKEY.'_pi3'] = 'layout,select_key,pages,recursive';

t3lib_div::loadTCA('fe_users');

t3lib_extMgm::addTCAcolumns('fe_users', Array(
	'registerdate' => array (
		'exclude' => 1,
		'label' => 'LLL:EXT:ke_userregister/locallang_db.xml:fe_users.registerdate',
		'config'  => array (
			'type' => 'input',
			'readOnly' => '1',
			'eval' => 'datetime',
			'size' => '12',
			'default'  => '0'
		)
	),
	'dayofbirth' => Array (
		'exclude' => 1,
		'label' => 'LLL:EXT:ke_userregister/locallang_db.xml:fe_users.dayofbirth',
		'config' => Array (
			'type' => 'select',
			'size' => '1',
			'max' => '1',
			'items' => make_selector(31),
			'eval' => 'trim',
			'default' => ''
		)
	),
	'monthofbirth' => Array (
		'exclude' => 1,
		'label' => 'LLL:EXT:ke_userregister/locallang_db.xml:fe_users.monthofbirth',
		'config' => Array (
			'type' => 'select',
			'size' => '1',
			'max' => '1',
			'items' => apply_strftime(make_selector(12)),
			'eval' => 'trim',
			'default' => ''
		)
	),
	'yearofbirth' => Array (
		'exclude' => 1,
		'label' => 'LLL:EXT:ke_userregister/locallang_db.xml:fe_users.yearofbirth',
		'config' => Array (
			'type' => 'input',
			'size' => '4',
			'max' => '4',
			'eval' => 'trim',
			'default' => ' '
		)
	),
));

$TCA['fe_users']['interface']['showRecordFieldList'] = str_replace('title,', 'gender,first_name,last_name,title,dayofbirth,monthofbirth,yearofbirth', $TCA['fe_users']['interface']['showRecordFieldList']);
$TCA['fe_users']['feInterface']['fe_admin_fieldList'] = str_replace(',title', ',gender,first_name,last_name,title,dayofbirth,monthofbirth,yearofbirth', $TCA['fe_users']['feInterface']['fe_admin_fieldList']);
$lastPalette = 0;
for ($i=0; $i<10; $i++)	{
	if (isset($TCA['fe_users']['palettes'][$i]) && is_array($TCA['fe_users']['palettes'][$i]))	{
		$lastPalette = $i;
	}
}

// add register date
t3lib_extMgm::addToAllTCAtypes('fe_users','registerdate','','after:usergroup');

// add birthday fields
t3lib_extMgm::addToAllTCAtypes("fe_users","dayofbirth, monthofbirth, yearofbirth", "", "after:name");


?>
