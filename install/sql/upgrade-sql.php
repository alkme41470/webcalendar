<?php
/*
 * This file replaces upgrade-*.sql files that show what SQL commands are required to
 * upgrade from one version of WebCalendar to another.
 * This file was introducted in v1.9.12 as part of the new installer.
 * The intent of this file is that by default the SQL for the upgrade will be
 * in the 'default-sql' field.  However, if any database needs different SQL,
 * there should be a db-specific key in the array like 'postgres-sql' or 'sqlite3-sql'.
 * NOTE: Currently only tested with MySQL.
 */

/**
 * Given a WebCalendar version (e.g. "v1.9.0"), find the array index within $updates where
 * the next version after it can be found ("1.9.1").
 */
function findUpgradeStartIndex($currentVersion)
{
  global $updates;
  // Normalize the version string to make it compatible with version_compare
  $normalizedCurrentVersion = str_replace('v', '', strtolower($currentVersion));

  foreach ($updates as $index => $update) {
    $normalizedUpdateVersion = str_replace('v', '', strtolower($update['version']));
    if (version_compare($normalizedUpdateVersion, $normalizedCurrentVersion, '>=')) {
      return $index;
    }
  }

  // If no update is found, return null
  return null;
}

function getSqlUpdates($currentVersion, $dbType = 'default', $includeFunctions = false)
{
  global $updates;
  if ($dbType == 'mysqli')
    $dbType = 'mysql'; // use the same SQL
  $startIndex = findUpgradeStartIndex($currentVersion);
  $sql = [];
  if ($startIndex >= 0 && $startIndex != null) {
    for ($i = $startIndex + 1; $i < count($updates); $i++) {
      $key = 'default-sql';
      if (isset($updates[$i][$dbType . '-sql'])) {
        $key = $dbType . '-sql';
      }
      //echo "updates $i $key => '" . $updates[$i][$key] . "'\n";
      $s = explode(';', rtrim($updates[$i][$key], ';'));
      for ($j = 0; $j < count($s); $j++) {
        $s[$j] = trim($s[$j]);
        //echo "$j => '" . $s[$j] . "'\n";
      }
      $sql = array_merge($sql, $s);
      // Add any upgrade function after the SQL changes
      if (isset($updates[$i]['upgrade-function'])) {
        $sql[] = 'function:' . $updates[$i]['upgrade-function'];
      }
    }
  }
  return $sql;
}

$updates = [
  [
    'version' => 'v0.9.22',
    'default-sql' => <<<'SQL'
UPDATE webcal_entry SET cal_time = -1 WHERE cal_time IS NULL;
ALTER TABLE webcal_entry MODIFY cal_time INT NOT NULL DEFAULT -1;
CREATE TABLE webcal_entry_repeats (
  cal_id INT NOT NULL,
  cal_days CHAR(7),
  cal_end INT,
  cal_frequency INT DEFAULT 1,
  cal_type VARCHAR(20),
  PRIMARY KEY (cal_id)
);
SQL
  ],
  [
    'version' => 'v0.9.27',
    'default-sql' => <<<'SQL'
CREATE TABLE webcal_user_layers (
  cal_login VARCHAR(25) NOT NULL,
  cal_layeruser VARCHAR(25) NOT NULL,
  cal_color VARCHAR(25),
  cal_dups CHAR(1) NOT NULL DEFAULT 'N',
  cal_layerid INT NOT NULL,
  PRIMARY KEY (cal_login,cal_layeruser)
);
SQL
  ],
  [
    'version' => 'v0.9.35',
    'default-sql' => <<<'SQL'
CREATE TABLE webcal_site_extras (
  cal_id INT NOT NULL,
  cal_name VARCHAR(25) NOT NULL,
  cal_type INT NOT NULL,
  cal_date INT,
  cal_remind INT,
  cal_data TEXT,
  PRIMARY KEY (cal_id,cal_name,cal_type)
);
SQL
  ],
  [
    'version' => 'v0.9.37',
    'default-sql' => <<<'SQL'
CREATE TABLE webcal_config (
  cal_setting VARCHAR(50) NOT NULL,
  cal_value VARCHAR(50),
  PRIMARY KEY (cal_setting)
);
CREATE TABLE webcal_entry_log (
  cal_log_id INT NOT NULL,
  cal_date INT NOT NULL,
  cal_entry_id INT NOT NULL,
  cal_login VARCHAR(25) NOT NULL,
  cal_time INT,
  cal_type CHAR(1) NOT NULL,
  cal_text TEXT,
  PRIMARY KEY (cal_log_id)
);
CREATE TABLE webcal_group (
  cal_group_id INT NOT NULL,
  cal_last_update INT NOT NULL,
  cal_name VARCHAR(50) NOT NULL,
  cal_owner VARCHAR(25),
  PRIMARY KEY (cal_group_id)
);
CREATE TABLE webcal_group_user (
  cal_group_id INT NOT NULL,
  cal_login VARCHAR(25) NOT NULL,
  PRIMARY KEY (cal_group_id,cal_login)
);
CREATE TABLE webcal_view (
  cal_view_id INT NOT NULL,
  cal_name VARCHAR(50) NOT NULL,
  cal_owner VARCHAR(25) NOT NULL,
  cal_view_type CHAR(1),
  PRIMARY KEY (cal_view_id)
);
CREATE TABLE webcal_view_user (
  cal_view_id INT NOT NULL,
  cal_login VARCHAR(25) NOT NULL,
  PRIMARY KEY (cal_view_id,cal_login)
);
SQL
  ],
  [
    'version' => 'v0.9.38',
    'default-sql' => <<<'SQL'
ALTER TABLE webcal_entry_log ADD cal_user_cal VARCHAR(25);
CREATE TABLE webcal_entry_repeats_not (
  cal_id INT NOT NULL,
  cal_date INT NOT NULL,
  PRIMARY KEY (cal_id,cal_date)
);
SQL
  ],
  [
    'version' => 'v0.9.40',
    'default-sql' => <<<'SQL'
ALTER TABLE webcal_entry_user ADD cal_category INT;
CREATE TABLE webcal_categories (
  cat_id INT NOT NULL,
  cat_name VARCHAR(80) NOT NULL,
  cat_owner VARCHAR(25),
  PRIMARY KEY (cat_id)
);
SQL
  ],
  [
    'version' => 'v0.9.41',
    'default-sql' => <<<'SQL'
DELETE FROM webcal_config WHERE cal_setting LIKE 'DATE_FORMAT%';
DELETE FROM webcal_user_pref WHERE cal_setting LIKE 'DATE_FORMAT%';
ALTER TABLE webcal_entry ADD cal_ext_for_id INT;
CREATE TABLE webcal_asst (
  cal_boss VARCHAR(25) NOT NULL,
  cal_assistant VARCHAR(25) NOT NULL,
  PRIMARY KEY (cal_boss,cal_assistant)
);
CREATE TABLE webcal_entry_ext_user (
  cal_id INT NOT NULL,
  cal_fullname VARCHAR(50) NOT NULL,
  cal_email VARCHAR(75),
  PRIMARY KEY (cal_id,cal_fullname)
);
SQL
  ],
  [
    'version' => 'v0.9.42',
    'default-sql' => <<<'SQL'
CREATE TABLE webcal_nonuser_cals (
  cal_login VARCHAR(25) NOT NULL,
  cal_admin VARCHAR(25) NOT NULL,
  cal_firstname VARCHAR(25),
  cal_lastname VARCHAR(25),
  PRIMARY KEY (cal_login)
);
SQL
  ],
  [
    'version' => 'v0.9.43',
    'default-sql' => <<<'SQL'
CREATE TABLE webcal_report (
  cal_report_id INT NOT NULL,
  cal_allow_nav CHAR(1) NOT NULL DEFAULT 'Y',
  cal_cat_id INT,
  cal_include_empty CHAR(1) NOT NULL DEFAULT 'N',
  cal_include_header CHAR(1) NOT NULL DEFAULT 'Y',
  cal_is_global CHAR(1) NOT NULL DEFAULT 'N',
  cal_login VARCHAR(25) NOT NULL,
  cal_report_name VARCHAR(50) NOT NULL,
  cal_report_type VARCHAR(20) NOT NULL,
  cal_show_in_trailer CHAR(1) NOT NULL DEFAULT 'N',
  cal_time_range INT NOT NULL,
  cal_update_date INT NOT NULL,
  cal_user VARCHAR(25),
  PRIMARY KEY (cal_report_id)
);
CREATE TABLE webcal_report_template (
  cal_report_id INT NOT NULL,
  cal_template_type CHAR(1) NOT NULL,
  cal_template_text TEXT,
  PRIMARY KEY (cal_report_id,cal_template_type)
);
SQL
  ],
  [
    'version' => 'v1.0RC3',
    'default-sql' => <<<'SQL'
ALTER TABLE webcal_user MODIFY cal_passwd VARCHAR(32);
DROP TABLE IF EXISTS webcal_import_data;
CREATE TABLE webcal_import (
  cal_import_id INT NOT NULL,
  cal_date INT NOT NULL,
  cal_login VARCHAR(25),
  cal_name VARCHAR(50),
  cal_type VARCHAR(10) NOT NULL,
  PRIMARY KEY (cal_import_id)
);
CREATE TABLE webcal_import_data (
  cal_id INT NOT NULL,
  cal_login VARCHAR(25) NOT NULL,
  cal_external_id VARCHAR(200),
  cal_import_id INT NOT NULL,
  cal_import_type VARCHAR(15) NOT NULL,
  PRIMARY KEY (cal_id,cal_login)
);
SQL
  ],
  [
    'version' => 'v1.1.0-CVS',
    'default-sql' => <<<'SQL'
UPDATE webcal_config SET cal_value = 'week.php'  WHERE cal_setting = 'STARTVIEW';
UPDATE webcal_user_pref SET cal_value = 'day.php'  WHERE cal_value = 'day' AND cal_setting = 'STARTVIEW';
UPDATE webcal_user_pref SET cal_value = 'month.php'  WHERE cal_value = 'month' AND cal_setting = 'STARTVIEW';
UPDATE webcal_user_pref SET cal_value = 'week.php'  WHERE cal_value = 'week' AND cal_setting = 'STARTVIEW';
UPDATE webcal_user_pref SET cal_value = 'year.php'  WHERE cal_value = 'year' AND cal_setting = 'STARTVIEW';
UPDATE webcal_view SET cal_is_global = 'N';
SQL
  ],
  [
    'version' => 'v1.1.0a-CVS',
    'default-sql' => <<<'SQL'
CREATE TABLE webcal_access_function (
  cal_login VARCHAR(25) NOT NULL,
  cal_permissions VARCHAR(64) NOT NULL,
  PRIMARY KEY (cal_login)
);
ALTER TABLE webcal_nonuser_cals ADD cal_is_public CHAR(1) NOT NULL DEFAULT 'N';
SQL
  ],
  [
    'version' => 'v1.1.0b-CVS',
    'default-sql' => <<<'SQL'
CREATE TABLE webcal_user_template (
  cal_login VARCHAR(25) NOT NULL,
  cal_type CHAR(1) NOT NULL,
  cal_template_text TEXT,
  PRIMARY KEY (cal_login,cal_type)
);
ALTER TABLE webcal_entry_repeats ADD cal_endtime INT(11) DEFAULT NULL;
ALTER TABLE webcal_entry_repeats ADD cal_bymonth VARCHAR(50) DEFAULT NULL;
ALTER TABLE webcal_entry_repeats ADD cal_bymonthday VARCHAR(100) DEFAULT NULL;
ALTER TABLE webcal_entry_repeats ADD cal_byday VARCHAR(100) DEFAULT NULL;
ALTER TABLE webcal_entry_repeats ADD cal_bysetpos VARCHAR(50) DEFAULT NULL;
ALTER TABLE webcal_entry_repeats ADD cal_byweekno VARCHAR(50) DEFAULT NULL;
ALTER TABLE webcal_entry_repeats ADD cal_byyearday VARCHAR(50) DEFAULT NULL;
ALTER TABLE webcal_entry_repeats ADD cal_wkst CHAR(2) DEFAULT 'MO';
ALTER TABLE webcal_entry_repeats ADD cal_count INT(11) DEFAULT NULL;
ALTER TABLE webcal_entry_repeats_not ADD cal_exdate INT(1) NOT NULL DEFAULT '1';
ALTER TABLE webcal_entry ADD cal_due_date INT(11) DEFAULT NULL;
ALTER TABLE webcal_entry ADD cal_due_time INT(11) DEFAULT NULL;
ALTER TABLE webcal_entry ADD cal_location VARCHAR(100) DEFAULT NULL;
ALTER TABLE webcal_entry ADD cal_url VARCHAR(100) DEFAULT NULL;
ALTER TABLE webcal_entry ADD cal_completed INT(11) DEFAULT NULL;
ALTER TABLE webcal_entry_user ADD cal_percent INT(11) NOT NULL DEFAULT '0';
ALTER TABLE webcal_site_extras DROP PRIMARY KEY;
SQL
  ],
  [
    'version' => 'v1.1.0c-CVS',
    'upgrade-function' => 'do_v11b_updates',
    'default-sql' => <<<'SQL'
CREATE TABLE webcal_entry_categories (
  cal_id int(11) NOT NULL DEFAULT '0',
  cat_id int(11) NOT NULL DEFAULT '0',
  cat_order int(11) NOT NULL DEFAULT '0',
  cat_owner VARCHAR(25) DEFAULT NULL
);
SQL
  ],
  [
    'version' => 'v1.1.0d-CVS',
    'default-sql' => <<<'SQL'
CREATE TABLE webcal_blob (
  cal_blob_id INT NOT NULL,
  cal_id INT NULL,
  cal_login VARCHAR(25) NULL,
  cal_name VARCHAR(30) NULL,
  cal_description VARCHAR(128) NULL,
  cal_size INT NULL,
  cal_mime_type VARCHAR(50) NULL,
  cal_type CHAR(1) NOT NULL,
  cal_mod_date INT NOT NULL,
  cal_mod_time INT NOT NULL,
  cal_blob BYTEA,
  PRIMARY KEY ( cal_blob_id )
);
SQL,
    'postgres-sql' => <<<'SQL'
CREATE TABLE webcal_blob (
  cal_blob_id INT NOT NULL,
  cal_id INT NULL,
  cal_login VARCHAR(25) NULL,
  cal_name VARCHAR(30) NULL,
  cal_description VARCHAR(128) NULL,
  cal_size INT NULL,
  cal_mime_type VARCHAR(50) NULL,
  cal_type CHAR(1) NOT NULL,
  cal_mod_date INT NOT NULL,
  cal_mod_time INT NOT NULL,
  cal_blob LONGBLOB,
  PRIMARY KEY ( cal_blob_id )
);
SQL
  ],
  [
    'version' => 'v1.1.0e-CVS',
    'upgrade-function' => 'do_v11e_updates',
    'default-sql' => <<<'SQL'
DROP TABLE IF EXISTS webcal_access_user;
CREATE TABLE webcal_access_user (
  cal_login VARCHAR(25) NOT NULL,
  cal_other_user VARCHAR(25) NOT NULL,
  cal_can_view INT NOT NULL DEFAULT '0',
  cal_can_edit INT NOT NULL DEFAULT '0',
  cal_can_approve INT NOT NULL DEFAULT '0',
  cal_can_invite CHAR(1) DEFAULT 'Y',
  cal_can_email CHAR(1) DEFAULT 'Y',
  cal_see_time_only CHAR(1) DEFAULT 'N',
  PRIMARY KEY ( cal_login, cal_other_user )
);
SQL
  ],
  [
    'version' => 'v1.1.1',
    'default-sql' => <<<'SQL'
CREATE TABLE webcal_reminders (
  cal_id INT NOT NULL DEFAULT '0',
  cal_date INT NOT NULL DEFAULT '0',
  cal_offset INT NOT NULL DEFAULT '0',
  cal_related CHAR(1) NOT NULL DEFAULT 'S',
  cal_before CHAR(1) NOT NULL DEFAULT 'Y',
  cal_last_sent INT NOT NULL DEFAULT '0',
  cal_repeats INT NOT NULL DEFAULT '0',
  cal_duration INT NOT NULL DEFAULT '0',
  cal_times_sent INT NOT NULL DEFAULT '0',
  cal_action VARCHAR(12) NOT NULL DEFAULT 'EMAIL',
  PRIMARY KEY ( cal_id )
);
SQL
  ],
  [
    'version' => 'v1.1.2',
    'default-sql' => <<<'SQL'
ALTER TABLE webcal_nonuser_cals ADD cal_url VARCHAR(255) DEFAULT NULL;
SQL
  ],
  [
    'version' => 'v1.1.3',
    'default-sql' => <<<'SQL'
ALTER TABLE webcal_categories ADD cat_color VARCHAR(8) DEFAULT NULL;
ALTER TABLE webcal_user ADD cal_enabled CHAR(1) DEFAULT 'Y';
ALTER TABLE webcal_user ADD cal_telephone VARCHAR(50) DEFAULT NULL;
ALTER TABLE webcal_user ADD cal_address VARCHAR(75) DEFAULT NULL;
ALTER TABLE webcal_user ADD cal_title VARCHAR(75) DEFAULT NULL;
ALTER TABLE webcal_user ADD cal_birthday INT NULL;
ALTER TABLE webcal_user ADD cal_last_login INT NULL;
SQL
  ],
  [
    'version' => 'v1.3.0',
    'default-sql' => <<<'SQL'
CREATE TABLE webcal_timezones (
  tzid VARCHAR(100) NOT NULL DEFAULT '',
  dtstart VARCHAR(25) DEFAULT NULL,
  dtend VARCHAR(25) DEFAULT NULL,
  vtimezone TEXT,
  PRIMARY KEY  ( tzid )
);
SQL
  ],
  [
    'version' => 'v1.9.0',
    'default-sql' => <<<'SQL'
CREATE INDEX webcal_entry_categories ON webcal_entry_categories(cat_id);
SQL
  ],
  [
    'version' => 'v1.9.1',
    'default-sql' => <<<'SQL'
ALTER TABLE webcal_import ADD cal_check_date INT NULL;
ALTER TABLE webcal_import ADD cal_md5 VARCHAR(32) NULL DEFAULT NULL;
CREATE INDEX webcal_import_data_type ON webcal_import_data(cal_import_type);
CREATE INDEX webcal_import_data_ext_id ON webcal_import_data(cal_external_id);
ALTER TABLE webcal_user MODIFY cal_passwd VARCHAR(255);
SQL
  ],
  [
    'version' => 'v1.9.6',
    'default-sql' => <<<'SQL'
UPDATE webcal_entry_categories SET cat_owner = '' WHERE cat_owner IS NULL;
ALTER TABLE webcal_entry_categories ADD PRIMARY KEY (cal_id, cat_id, cat_order, cat_owner);
SQL
  ],
  [
    'version' => 'v1.9.11',
    'upgrade-function' => 'do_v1_9_11_updates',
    'default-sql' => <<<'SQL'
ALTER TABLE webcal_categories ADD cat_status CHAR DEFAULT 'A';
ALTER TABLE webcal_categories ADD cat_icon_mime VARCHAR(32) DEFAULT NULL;
ALTER TABLE webcal_categories ADD cat_icon_blob LONGBLOB DEFAULT NULL;
ALTER TABLE webcal_categories MODIFY cat_owner VARCHAR(25) DEFAULT '' NOT NULL;
SQL,
    'postgres-sql' => <<<'SQL'
ALTER TABLE webcal_categories ADD COLUMN cat_status CHAR DEFAULT 'A';
ALTER TABLE webcal_categories ADD COLUMN cat_icon_mime VARCHAR(32) DEFAULT NULL;
ALTER TABLE webcal_categories ADD COLUMN cat_icon_blob BYTEA DEFAULT NULL;
ALTER TABLE webcal_categories MODIFY cat_owner VARCHAR(25) DEFAULT '' NOT NULL;
SQL
  ],
  [
    'version' => 'v1.9.12',
    'default-sql' => <<<'SQL'
ALTER TABLE webcal_nonuser_cals MODIFY COLUMN cal_url VARCHAR(255);
ALTER TABLE webcal_entry MODIFY COLUMN cal_url VARCHAR(255);
SQL
  ],
];
