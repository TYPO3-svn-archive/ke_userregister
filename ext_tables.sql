#
# Table structure for table 'tx_keuserregister_hash'
#
CREATE TABLE tx_keuserregister_hash (
	hash tinytext,
	feuser_uid tinytext,
    new_email tinytext,
    tstamp int(11) DEFAULT '0' NOT NULL,
);