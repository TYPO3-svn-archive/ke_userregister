#
# Table structure for table 'tx_keuserregister_hash'
#
CREATE TABLE tx_keuserregister_hash (
	hash tinytext,
	feuser_uid tinytext,
    new_email tinytext,
    tstamp int(11) DEFAULT '0' NOT NULL,
);

# extend fe_users table
CREATE TABLE fe_users (
	gender int(11) unsigned DEFAULT '0' NOT NULL,
	first_name varchar(50) DEFAULT '' NOT NULL,
	last_name varchar(50) DEFAULT '' NOT NULL,
	registerdate int(11) DEFAULT '0' NOT NULL
);
