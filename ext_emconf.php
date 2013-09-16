<?php

########################################################################
# Extension Manager/Repository config file for ext "ke_userregister".
#
# Auto generated 10-10-2012 12:41
#
# Manual updates:
# Only the data in the array - everything else is removed by next
# writing. "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'Frontend User Registration',
	'description' => 'Frontend User Registration; create and edit user profiles, change passwords and delete profiles; supports md5 and salted passwords',
	'category' => 'plugin',
	'author' => 'A. Kiefer, C. Buelter (kennziffer.com)',
	'author_email' => 'kiefer@kennziffer.com',
	'shy' => '',
	'dependencies' => '',
	'conflicts' => '',
	'priority' => '',
	'module' => '',
	'state' => 'stable',
	'internal' => '',
	'uploadfolder' => 1,
	'createDirs' => '',
	'modify_tables' => '',
	'clearCacheOnLoad' => 0,
	'lockType' => '',
	'author_company' => 'www.kennziffer.com GmbH',
	'version' => '0.1.4',
	'constraints' => array(
		'depends' => array(
			'typo3' => '4.5.0-6.1.99',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
	'suggests' => array(
	),
	'_md5_values_when_last_written' => 'a:29:{s:9:"ChangeLog";s:4:"1173";s:10:"README.txt";s:4:"ee2d";s:22:"birthdayFields.php.inc";s:4:"3aba";s:12:"ext_icon.gif";s:4:"8ca4";s:17:"ext_localconf.php";s:4:"963d";s:14:"ext_tables.php";s:4:"d25e";s:14:"ext_tables.sql";s:4:"50e0";s:15:"flexform_ds.xml";s:4:"2f5d";s:16:"locallang_db.xml";s:4:"e34d";s:7:"tca.php";s:4:"8105";s:14:"doc/manual.sxw";s:4:"ec93";s:19:"doc/wizard_form.dat";s:4:"32ee";s:20:"doc/wizard_form.html";s:4:"2c3f";s:42:"lib/class.tx_keuserregister_cms_layout.php";s:4:"7d9c";s:35:"lib/class.tx_keuserregister_lib.php";s:4:"55a0";s:35:"pi1/class.tx_keuserregister_pi1.php";s:4:"df89";s:17:"pi1/locallang.xml";s:4:"3713";s:15:"pi1/pi_icon.gif";s:4:"0552";s:35:"pi2/class.tx_keuserregister_pi2.php";s:4:"e982";s:17:"pi2/locallang.xml";s:4:"69e4";s:15:"pi2/pi_icon.gif";s:4:"05fb";s:35:"pi3/class.tx_keuserregister_pi3.php";s:4:"79a4";s:17:"pi3/locallang.xml";s:4:"18f0";s:15:"pi3/pi_icon.gif";s:4:"e78a";s:27:"res/css/ke_userregister.css";s:4:"9fff";s:25:"res/scripts/RemoveXSS.php";s:4:"4252";s:35:"res/template/tx_keuserregister.tmpl";s:4:"03d7";s:23:"static/ts/constants.txt";s:4:"d41d";s:19:"static/ts/setup.txt";s:4:"3408";}',
);

?>