#
# Table structure for table 'tx_powermail_domain_model_form'
#
CREATE TABLE tx_powermail_domain_model_form
(
	`sf_enable`         tinyint(3) DEFAULT '0' NOT NULL,
	`sf_oid`            tinytext,
	`sf_mode`           varchar(20) DEFAULT 'web2lead' NOT NULL,
	`sf_record_type_id` varchar(100) DEFAULT '' NOT NULL
);

#
# Table structure for table 'tx_powermail_domain_model_field'
#
CREATE TABLE tx_powermail_domain_model_field
(
	`sf_fieldname` varchar(100) DEFAULT '' NOT NULL
);
