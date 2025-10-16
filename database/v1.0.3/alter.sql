ALTER TABLE device_mappings ADD uuid VARCHAR(255) NOT NULL DEFAULT '' AFTER fingerprint_skip;
ALTER TABLE device_status ADD is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER device_id;
ALTER TABLE device_logs ADD is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER device_id;
ALTER TABLE device_activity ADD is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER device_id;