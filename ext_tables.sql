#
# Table structure for table 'tx_st9fissync_resync_entries'
# To be removed**
#
CREATE TABLE tx_st9fissync_resync_entries (
	uid int(11) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,
	tstamp int(11) DEFAULT '0' NOT NULL,
	crdate int(11) DEFAULT '0' NOT NULL,
	cruser_id int(11) DEFAULT '0' NOT NULL,
	record_table tinytext,
	record_uid int(11) DEFAULT '0' NOT NULL,
	record_action int(11) DEFAULT '0' NOT NULL,
	record_tstamp int(11) DEFAULT '0' NOT NULL,
	
	PRIMARY KEY (uid),
	KEY parent (pid)
);

#
# Table structure for table 'tx_st9fissync_confighistory'
#
CREATE TABLE tx_st9fissync_confighistory (

	uid int(11) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,
	crdate int(11) DEFAULT '0' NOT NULL,
	config text NOT NULL,
	
	PRIMARY KEY (uid),
	KEY parent (pid),
	KEY crdate (crdate)
	
);

#
# Table structure for table 'tx_st9fissync_dbversioning_query'
#
CREATE TABLE tx_st9fissync_dbversioning_query (

	uid bigint(20) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,
	sysid  int(11) DEFAULT '0' NOT NULL,
	crdate int(11) unsigned DEFAULT '0' NOT NULL,
	crmsec bigint(20) unsigned DEFAULT '0' NOT NULL,
	timestamp int(11) unsigned DEFAULT '0' NOT NULL,
	cruser_id int(11) unsigned DEFAULT '0' NOT NULL,
	updtuser_id int(11) unsigned DEFAULT '0' NOT NULL,
	query_text mediumtext NOT NULL,
	query_type  tinyint(4) unsigned DEFAULT '0' NOT NULL,
	query_affectedrows int(11) unsigned DEFAULT '0' NOT NULL,
	query_info text NOT NULL,
	query_exectime int(11) unsigned DEFAULT '0' NOT NULL,
	query_error_number int(11) unsigned DEFAULT '0' NOT NULL,
	query_error_message text NOT NULL,
	workspace int(11) DEFAULT NULL,
	typo3_mode int(11) unsigned DEFAULT '0' NOT NULL,
	updt_typo3_mode int(11) unsigned DEFAULT '0' NOT NULL,
	issynced tinyint(4) unsigned DEFAULT '0' NOT NULL,
	issyncscheduled tinyint(4) unsigned DEFAULT '0' NOT NULL,
	request_url text NOT NULL,
	client_ip int(11) DEFAULT '0',	
	tables int(11) unsigned DEFAULT '0' NOT NULL,
	rootid int(11) DEFAULT '0' NOT NULL,
	excludeid int(11) DEFAULT '0' NOT NULL,
	
	PRIMARY KEY (uid),
	KEY parent (pid),
	KEY crdate (crdate),
	KEY crmsec (crmsec)
);

#
# Table structure for table 'tx_st9fissync_dbversioning_query_tablerows_mm'
# later we can have a affected tablerow table  (maybe a way to save the original values before update)
#
CREATE TABLE tx_st9fissync_dbversioning_query_tablerows_mm ( 

	uid_local int(11) unsigned DEFAULT '0' NOT NULL,
	uid_foreign int(11) unsigned DEFAULT '0' NOT NULL,
	recordRevision longtext NOT NULL,
	tablenames varchar(100) DEFAULT '' NOT NULL,
	sorting int(11) unsigned DEFAULT '0' NOT NULL,
	
	KEY uid_local (uid_local),
	KEY uid_foreign (uid_foreign)

);

#
# Table structure for table 'tx_st9fissync_dbsequencer_sequencer'
#
CREATE TABLE tx_st9fissync_dbsequencer_sequencer (

	tablename varchar(100) DEFAULT '' NOT NULL,
	current int(30) DEFAULT '0' NOT NULL,
	offset int(30) DEFAULT '1' NOT NULL,
	timestamp int(30) DEFAULT '0' NOT NULL,
	changed int(11) DEFAULT '0' NOT NULL,
	
	UNIQUE KEY tablename (tablename)
);

#
# Table structure for table 'tx_st9fissync_process'
#
CREATE TABLE tx_st9fissync_process (

	uid bigint(20) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,
	
	#who triggered a sync?
	cruser_id int(11) unsigned DEFAULT '0' NOT NULL,
	
	#sync triggered from
	syncproc_src_sysid  int(11) DEFAULT '0' NOT NULL,
	
	#sync to sysId
	syncproc_dest_sysid  int(11) DEFAULT '0' NOT NULL,
	
	syncproc_starttime int(11) unsigned DEFAULT '0' NOT NULL,
	
 	syncproc_endtime int(11) unsigned DEFAULT '0' NOT NULL,
	
	#for now RUNNING/FINISHED
	syncproc_stage int(11) unsigned DEFAULT '0' NOT NULL,
	
	#for now SUCCESS/FAIL?
	syncproc_status int(11) DEFAULT '0' NOT NULL,
		
	typo3_mode int(11) unsigned DEFAULT '0' NOT NULL,
	request_url text NOT NULL,
	client_ip int(11) unsigned DEFAULT '0',
	
	#count of requests per sync process triggered
	requests int(11) unsigned DEFAULT '0' NOT NULL,	
	
	PRIMARY KEY (uid),
	KEY parent (pid)
);

#
# Table structure for table 'tx_st9fissync_request'
#
CREATE TABLE tx_st9fissync_request (

	uid bigint(20) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,
	
	#when was the request sent
	request_sent_tstamp int(11) unsigned DEFAULT '0' NOT NULL,
	
	#what was the request that was sent
	request_sent mediumtext NOT NULL,
	
	#when was the received response
	response_received_tstamp int(11) unsigned DEFAULT '0' NOT NULL,
	
	#what was the response that was received
	response_received  mediumtext NOT NULL,
	
	#as part of which sync proc
	procid int(11) DEFAULT '0' NOT NULL,
	
	#which is the remote handler
	remote_handle int(11) DEFAULT '0' NOT NULL,
	
	#sync/re-sync
	sync_type int(11) unsigned DEFAULT '0' NOT NULL,
	
	#no. of recordings assimillated per request packet
	versionedqueries int(11) unsigned DEFAULT '0' NOT NULL,	
	
	PRIMARY KEY (uid),
	KEY parent (pid)
);

#
# Table structure for tx_st9fissync_request_dbversioning_query_mm
#
CREATE TABLE tx_st9fissync_request_dbversioning_query_mm ( 

	#corresponding request uid
	uid_local int(11) unsigned DEFAULT '0' NOT NULL,
	
	#versioned query uid
	uid_foreign int(11) unsigned DEFAULT '0' NOT NULL,

	#currently tx_st9fissync_dbversioning_query
	tablenames varchar(100) DEFAULT '' NOT NULL,
	
	#is the versioned query from remote or local/central
	isremote  int(11) DEFAULT '0' NOT NULL,
	
	#escalation factors
	escalated int(11) DEFAULT '0' NOT NULL,
	
	escalation_criticality int(11) DEFAULT '0' NOT NULL,	
	
	#associated error if any
	error_message mediumtext NOT NULL,
	
	sorting int(11) unsigned DEFAULT '0' NOT NULL,
	
	KEY uid_local (uid_local),
	KEY uid_foreign (uid_foreign)
);

#
# Table structure for table 'tx_st9fissync_request_handler'
#
CREATE TABLE tx_st9fissync_request_handler (

	uid bigint(20) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,

	#when was the request received
	request_received_tstamp int(11) unsigned DEFAULT '0' NOT NULL,
	
	#what was the request that was sent
	request_received longblob NOT NULL,
	
	#when was the response sent
	response_sent_tstamp int(11) unsigned DEFAULT '0' NOT NULL,
	
	#what was the response that was received
	response_sent  longblob NOT NULL,	
	
	PRIMARY KEY (uid),
	KEY parent (pid)
);

#
# Table structure for table 'tx_st9fissync_log'
#
CREATE TABLE tx_st9fissync_log (

  uid int(11) NOT NULL auto_increment,
  pid int(11) NOT NULL DEFAULT '0',
  sysid int(11) NOT NULL DEFAULT '0',
  crdate int(11) unsigned NOT NULL DEFAULT '0',
  log_message mediumtext NOT NULL,
  log_stage int(11) unsigned NOT NULL DEFAULT '0',
  log_priority int(11) unsigned NOT NULL DEFAULT '0',
  #as part of which sync proc
  procid int(11) DEFAULT '0' NOT NULL,
 
  PRIMARY KEY (uid),
  KEY parent (pid),
  KEY crdate (crdate)
);

#
# Table structure for table 'tx_st9fissync_gc_process'
#
CREATE TABLE tx_st9fissync_gc_process (

  uid int(11) NOT NULL auto_increment,
  pid int(11) NOT NULL DEFAULT '0',
  crdate int(11) unsigned NOT NULL DEFAULT '0',
  timestamp int(11) unsigned NOT NULL DEFAULT '0',
  gcproc_src_sysid  int(11) DEFAULT '0' NOT NULL,
  gcproc_dest_sysid  int(11) DEFAULT '0' NOT NULL,
  folderpath varchar(100) DEFAULT '' NOT NULL,
  remotefolderpath varchar(100) DEFAULT '' NOT NULL,
  gc_stage int(11) NOT NULL DEFAULT '0',
  gc_status int(11) NOT NULL DEFAULT '0', 
  
  
  PRIMARY KEY (uid),
  KEY parent (pid),
  KEY crdate (crdate)
);

#
# Table structure for table 'tx_st9fissync_gc_log'
#
CREATE TABLE tx_st9fissync_gc_log (

  uid int(11) NOT NULL auto_increment,
  pid int(11) NOT NULL DEFAULT '0',
  sysid int(11) NOT NULL DEFAULT '0',
  crdate int(11) unsigned NOT NULL DEFAULT '0',
  log_message longblob NOT NULL,
  log_stage int(11) unsigned NOT NULL DEFAULT '0',
  log_priority int(11) unsigned NOT NULL DEFAULT '0',
  #as part of which gc sync proc
  procid int(11) DEFAULT '0' NOT NULL,
 
  PRIMARY KEY (uid),
  KEY parent (pid),
  KEY crdate (crdate)
);

#
#bigint issues: http://bugs.mysql.com/bug.php?id=11215
#http://dev.mysql.com/doc/refman/5.0/en/numeric-types.html
#http://forums.devarticles.com/mysql-development-50/need-help-with-mysql-error-invalid-default-value-for-account-176275.html