<?php

/**
 * @method DbTable_Row_Contact createRow() createRow(array $data)
 */
class DbTable_Contacts extends DbTable_Abstract
{
    const NOT_FOUND_EMAIL = 'NOT FOUND';

    protected $_primary  = 'contact_id';
    protected $_name     = 'contacts';
    protected $_sequence = false;
    protected $_rowClass = 'DbTable_Row_Contact';

    /**
     * Creates an instance of this table dynamically
     *
     * @param bool $drop
     */
    public function createTable($drop = false)
    {
        $sql = sprintf("
            CREATE TABLE IF NOT EXISTS `@table_name@` (
                `contact_id` varbinary(20) NOT NULL,
                `contact_email` varchar(128) NOT NULL,
				`campaign_cd` varchar(128) NULL DEFAULT NULL,
				`cell_cd` varchar(128) NULL DEFAULT NULL,
				`rundate` datetime NULL DEFAULT NULL,
                `first_name`  varchar(128) NULL DEFAULT NULL,
                `last_name`  varchar(128) NULL DEFAULT NULL,
                `status` enum('active','onboarding','transactional','bounce','unconfirmed','unsub') NOT NULL,
                `msg_pref` enum('text','html') NOT NULL,
                `source` enum('manual','import','api','webform','sforcereport') NOT NULL,
                `custom_source` enum('text','html') DEFAULT NULL,
                `created_at` datetime NOT NULL,
                `soft_bounce_count` int(10) unsigned NOT NULL DEFAULT '0',
                `soft_bounce_last_date` datetime DEFAULT NULL,
                `hard_bounce_count` int(10) unsigned NOT NULL DEFAULT '0',
                `hard_bounce_last_date` datetime DEFAULT NULL,
                `valid` enum('Y','N') NOT NULL DEFAULT 'Y',
                `customer_number` varchar(255) DEFAULT NULL,
                `added_at`  datetime NOT NULL,
                `modified_at`  datetime NULL DEFAULT NULL,
              PRIMARY KEY (`contact_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Created %s';",
                date('Y-m-d H:i:s')
        );

        return parent::_createTable($drop, $sql);
    }

    /**
     * @param string $filePath
     */
    public function exportCsv($filePath, $fromTs = null, $toTs = null)
    {
        $db    = $this->getAdapter();
        $table = ($this->_schema ? $this->_schema . '.' : '') . $this->_name;

        $betweenSql = false;
        if (!empty($fromTs) && !empty($toTs)) {
            $sqlFrom = date('Y-m-d H:i:s', $fromTs);
            $sqlTo   = date('Y-m-d H:i:s', $toTs);

            $betweenSql = false;
            $betweenSql = sprintf("
                WHERE (created_at BETWEEN '%s' AND '%s')",
                    $sqlFrom, $sqlTo
            );
        }

        $sql = sprintf("
            SELECT IFNULL(customer_number, ''),
                   IFNULL(contact_email, '%s'),
                   IFNULL(status, ''),
                   IFNULL(created_at, ''),
                   IFNULL(custom_source, IFNULL(source, '')),
                   IFNULL(CONCAT(first_name, ' ', last_name), ''),
                   IFNULL(soft_bounce_count, ''),
                   IFNULL(soft_bounce_last_date, ''),
                   IFNULL(hard_bounce_count, ''),
                   IFNULL(hard_bounce_last_date, ''),
                   IFNULL(valid, 'Y')
            INTO OUTFILE '%s'
            FIELDS
                TERMINATED BY '|'
                OPTIONALLY ENCLOSED BY '\"'
            LINES
                TERMINATED BY '\\n'
            FROM `%s` AS r
            %s",
                DbTable_Contacts::NOT_FOUND_EMAIL,
                addslashes($filePath),
                $table,
                $betweenSql ? $betweenSql : null
        );

        $db->getConnection()->exec($sql);

        // Prepend headers
        $headers = array(
            'customer_number',
            'contact_email',
            'status',
            'created_at',
            'source',
            'name',
            'soft_bounce_count',
            'soft_bounce_last_date',
            'hard_bounce_count',
            'hard_bounce_last_date',
            'valid',
        );

        $headers  = implode('|', $headers) . PHP_EOL;
        $handle   = fopen($filePath, 'r+');
        $len      = strlen($headers);
        $finalLen = filesize($filePath) + $len;

        $cacheNew = $headers;
        $cacheOld = fread($handle, $len);
        rewind($handle);

        $i = 1;
        while (ftell($handle) < $finalLen) {
            fwrite($handle, $cacheNew);
            $cacheNew = $cacheOld;
            $cacheOld = fread($handle, $len);
            fseek($handle, $i * $len);
            $i++;
        }

        fclose($handle);
    }
}