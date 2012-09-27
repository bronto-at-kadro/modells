<?php

/**
 * @method DbTable_Row_Activities createRow() createRow(array $data)
 */
class DbTable_Activities extends DbTable_Abstract
{
    const TABLE_NAME_FORMAT = 'activities_%04d_%02d';

    protected $_primary  = array('contact_id', 'message_id', 'delivery_id');
    protected $_name     = null;
    protected $_sequence = false;
    protected $_rowClass = 'DbTable_Row_Activity';

    private $_year;
    private $_month;

    /**
     * @param array $config
     * @throws Exception
     */
    public function __construct($config = array())
    {
        // Year
        if (isset($config['year'])) {
            $this->_year = sprintf("%04d", $config['year']);
            if ($this->_year < 2000) {
                throw new Exception("Year value ({$this->_year}) for Activities table is invalid.");
            }
            unset($config['year']);
        } else {
            throw new Exception('Year value for Activities table is required.');
        }

        // Month
        if (isset($config['month'])) {
            $this->_month = sprintf("%02d", $config['month']);
            if (!in_array($this->_month, array('01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'))) {
                throw new Exception('Month value for Activities table is invalid.');
            }
            unset($config['month']);
        } else {
            throw new Exception('Month value for Activities table is required.');
        }

        // Configure table name
        $this->_name = sprintf(self::TABLE_NAME_FORMAT, $this->_year, $this->_month);

        parent::__construct($config);
    }

    /**
     * Initialize object
     *
     * Called from {@link __construct()} as final step of object instantiation.
     *
     * @return void
     */
    public function init()
    {
        // Attempt table creation
        $this->createTable();
    }

    /**
     * Creates an instance of this table dynamically
     *
     * @param bool $drop
     */
    public function createTable($drop = false)
    {
	/*
		$sql = sprintf("
            CREATE TABLE IF NOT EXISTS `@table_name@` (
              `contact_id` varbinary(20) NOT NULL,
              `message_id` varbinary(20) NOT NULL,
              `message_name` varchar(128) DEFAULT NULL,
              `delivery_id`  varbinary(20) NULL DEFAULT NULL,
              `delivery_group_id` varbinary(20) NULL DEFAULT NULL,
              `delivery_group_name` varchar(128) NULL DEFAULT NULL,
              `sent_at` datetime NULL DEFAULT NULL,
              `opened_count` tinyint(2) unsigned NOT NULL DEFAULT '0',
              `opened_at_first` datetime DEFAULT NULL,
              `opened_at_last` datetime DEFAULT NULL,
              `clicked_count` tinyint(2) unsigned NOT NULL DEFAULT '0',
              `clicked_at_first` datetime DEFAULT NULL,
              `clicked_at_last` datetime DEFAULT NULL,
			  `activity_type` varchar(128) NOT NULL,
              PRIMARY KEY (`contact_id`,`message_id`,`delivery_id`, `activity_type`,`sent_at`),
              KEY `IX_%04d_%02d_sent_at` (`sent_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Created %s';",
                $this->_year, $this->_month,
                date('Y-m-d H:i:s')
        );
		*/
		
		$sql = sprintf("
            CREATE TABLE IF NOT EXISTS `@table_name@` (
              `contact_id` varbinary(20) NOT NULL,
              `message_id` varbinary(20) NOT NULL,
              `message_name` varchar(128) DEFAULT NULL,
              `delivery_id`  varbinary(20) NULL DEFAULT NULL,
              `delivery_group_id` varbinary(20) NULL DEFAULT NULL,
              `delivery_group_name` varchar(128) NULL DEFAULT NULL,
              `sent_at` datetime NULL DEFAULT NULL,
              `opened_count` tinyint(2) unsigned NOT NULL DEFAULT '0',
              `opened_at_first` datetime DEFAULT NULL,
              `opened_at_last` datetime DEFAULT NULL,
              `clicked_count` tinyint(2) unsigned NOT NULL DEFAULT '0',
              `clicked_at_first` datetime DEFAULT NULL,
              `clicked_at_last` datetime DEFAULT NULL,
			  `activity_type` varchar(128) NOT NULL,
			  `id` int NOT NULL AUTO_INCREMENT,
              PRIMARY KEY (`id`),
              KEY `IX_%04d_%02d_sent_at` (`sent_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Created %s';",
                $this->_year, $this->_month,
                date('Y-m-d H:i:s')
        );
		

        return parent::_createTable($drop, $sql);
    }

    
	/**
     * @return Zend_Db_Table_Rowset
     */
    public function getAllContactIds($limit = 100, $offset = 0, $count = false)
    {
        $select = $this->select()
            ->setIntegrityCheck(false)
            ->distinct();

        if ($count) {
            $select->from(array('r' => $this->info('name')), array('total' => new Zend_Db_Expr('COUNT(*)')))
                ->where('r.contact_id IS NOT NULL');
            $result = $this->fetchRow($select);
            return $result->total;
        }

        $select->from(array('r' => $this->info('name')), array('r.contact_id'))
            ->where('r.contact_id IS NOT NULL')
            ->limit($limit, $offset);

        $result = $this->fetchAll($select);
        if ($result->count() == 0) {
            return false;
        }
        return $result;
    }
	
	
	/**
     * @return Zend_Db_Table_Rowset
     */
    public function findWithNoEmail($limit = 100, $offset = 0, $count = false)
    {
        $select = $this->select()
            ->setIntegrityCheck(false)
            ->distinct();

        if ($count) {
            $select->from(array('r' => $this->info('name')), array('total' => new Zend_Db_Expr('COUNT(*)')))
                ->joinLeft(array('c' => 'contacts'), 'c.contact_id = r.contact_id', null)
                ->where('c.contact_email IS NULL');
            $result = $this->fetchRow($select);
            return $result->total;
        }

        $select->from(array('r' => $this->info('name')), array('r.contact_id'))
            ->joinLeft(array('c' => 'contacts'), 'c.contact_id = r.contact_id', null)
            ->where('c.contact_email IS NULL')
            ->limit($limit, $offset);

        $result = $this->fetchAll($select);
        if ($result->count() == 0) {
            return false;
        }
        return $result;
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

            $betweenSql = sprintf("
                WHERE (
                    sent_at BETWEEN '%s' AND '%s' OR
                    opened_at_last BETWEEN '%s' AND '%s' OR
                    clicked_at_last BETWEEN '%s' AND '%s'
                )",
                    $sqlFrom, $sqlTo,
                    $sqlFrom, $sqlTo,
                    $sqlFrom, $sqlTo,
                    $sqlFrom, $sqlTo
            );
        }
		
		
		/*
		$headers = array(
            'contact_id',
            'contact_email',
            'message_name',
            'campaign_cd',
            'cell_cd',
            'rundate',
            'action_type_desc',
            'action_type_code',
            'action_date'
        );
		*/

		//right here
		$sql = sprintf("
            SELECT IFNULL(c.contact_id, ''),
                   IFNULL(contact_email, '%s'),
                   IFNULL(message_name, ''),
                   IFNULL(campaign_cd, ''),
                   IFNULL(cell_cd, ''),
                   IFNULL(sent_at, ''),
                   IFNULL(rundate, ''),
                   IFNULL(opened_at_first, ''),
                   IFNULL(opened_at_last, ''),
                   IFNULL(clicked_count, ''),
                   IFNULL(clicked_at_first, ''),
                   IFNULL(clicked_at_last, ''),
				   IFNULL(activity_type, '')
            INTO OUTFILE '%s'
            FIELDS
                TERMINATED BY '|'
                OPTIONALLY ENCLOSED BY '\"'
            LINES
                TERMINATED BY '\\n'
            FROM `%s` AS r
            LEFT JOIN `contacts` AS c ON c.contact_id = r.contact_id
            %s",
                DbTable_Contacts::NOT_FOUND_EMAIL,
                addslashes($filePath),
                $table,
                $betweenSql ? $betweenSql : null
        );
		
		
		/*
	    $select = $this->select();
        $select->from($this, array('delivery_from', 'delivery_to', new Zend_Db_Expr('COUNT(*)')));
        $select->where('export_started_at IS NULL');
        $select->where('message IS NULL');
        $select->order('delivery_from ASC');
        $select->group(array('delivery_from', 'delivery_to'));
        $select->having('COUNT(*) = 4');
        $select->limit(1);
		
		
		
		$select = $this->select();
		$select->from($this, array('c.contact_id', 'contact_email', 
		*/
		
		
		/*
		$sql = sprintf("
            SELECT IFNULL(contact_id, ''),
                   IFNULL(contact_email, '%s'),
                   IFNULL(message_name, ''),
                   IFNULL(delivery_group_id, ''),
                   IFNULL(delivery_group_name, ''),
                   IFNULL(sent_at, ''),
                   IFNULL(opened_count, ''),
                   IFNULL(opened_at_first, ''),
                   IFNULL(opened_at_last, ''),
                   IFNULL(clicked_count, ''),
                   IFNULL(clicked_at_first, ''),
                   IFNULL(clicked_at_last, ''),
				   IFNULL(activity_type, '')
            INTO OUTFILE '%s'
            FIELDS
                TERMINATED BY '|'
                OPTIONALLY ENCLOSED BY '\"'
            LINES
                TERMINATED BY '\\n'
            FROM `%s` AS r
            LEFT JOIN `contacts` AS c ON c.contact_id = r.contact_id
            %s",
                DbTable_Contacts::NOT_FOUND_EMAIL,
                addslashes($filePath),
                $table,
                $betweenSql ? $betweenSql : null
        );*/
		
		
		//print_r($db->getConnection());
		//exit();
		

        $db->getConnection()->exec($sql);

        // Prepend headers
		/*
        $headers = array(
            'customer_number',
            'contact_email',
            'message_name',
            'delivery_group_id',
            'delivery_group_name',
            'sent_at',
            'opened_count',
            'opened_at_first',
            'opened_at_last',
            'clicked_count',
            'clicked_at_first',
            'clicked_at_last',
			'activity_type'
        );
		*/
		$headers = array(
            'contact_id',
            'contact_email',
            'message_name',
            'campaign_cd',
            'cell_cd',
            'rundate',
            'action_type_desc',
            'action_type_code',
            'action_date'
        );
		
		//exit();

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
