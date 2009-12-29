<?php
if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}

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

// setup first_name, last_name and gender
t3lib_div::loadTCA('fe_users');

t3lib_extMgm::addTCAcolumns('fe_users', Array(
	'first_name' => Array (
		'exclude' => 0,
		'label' => 'LLL:EXT:ke_userregister/locallang_db.xml:fe_users.first_name',
		'config' => Array (
			'type' => 'input',
			'size' => '20',
			'max' => '50',
			'eval' => 'trim',
			'default' => ''
		)
	),
	'last_name' => Array (
		'exclude' => 0,
		'label' => 'LLL:EXT:ke_userregister/locallang_db.xml:fe_users.last_name',
		'config' => Array (
			'type' => 'input',
			'size' => '20',
			'max' => '50',
			'eval' => 'trim',
			'default' => ''
		)
	),
	'gender' => Array (
		'exclude' => 0,
		'label' => 'LLL:EXT:ke_userregister/locallang_db.xml:fe_users.gender',
		'config' => Array (
			'type' => 'radio',
			'items' => Array (
				Array('LLL:EXT:ke_userregister/locallang_db.xml:fe_users.gender.I.0', '0'),
				Array('LLL:EXT:ke_userregister/locallang_db.xml:fe_users.gender.I.1', '1')
			),
		)
	),
));

$TCA['fe_users']['interface']['showRecordFieldList'] = str_replace('title,', 'gender,first_name,last_name,title,', $TCA['fe_users']['interface']['showRecordFieldList']);

$TCA['fe_users']['feInterface']['fe_admin_fieldList'] = str_replace(',title', ',gender,first_name,last_name,title', $TCA['fe_users']['feInterface']['fe_admin_fieldList']);
$lastPalette = 0;
for ($i=0; $i<10; $i++)	{
	if (isset($TCA['fe_users']['palettes'][$i]) && is_array($TCA['fe_users']['palettes'][$i]))	{
		$lastPalette = $i;
	}
}

$TCA['fe_users']['palettes'][$lastPalette+1]['showitem'] = 'gender,first_name';
$TCA['fe_users']['types']['0']['showitem'] = str_replace(', name', ',last_name;;'.($lastPalette+1).';;1-1-1, name', $TCA['fe_users']['types']['0']['showitem']);
$TCA['fe_users']['ctrl']['thumbnail'] = 'image';
?>
