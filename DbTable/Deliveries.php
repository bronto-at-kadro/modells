<?php

/**
 * @method DbTable_Row_Deliveries createRow() createRow(array $data)
 */
class DbTable_Deliveries extends DbTable_Abstract
{
    const TABLE_NAME_FORMAT = 'deliveries_%04d_%02d';

    protected $_primary  = array('id');
    protected $_name     = null;
    protected $_sequence = false;
    protected $_rowClass = 'DbTable_Row_Delivery';

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
                throw new Exception("Year value ({$this->_year}) for Deliveries table is invalid.");
            }
            unset($config['year']);
        } else {
            throw new Exception('Year value for Deliveries table is required.');
        }

        // Month
        if (isset($config['month'])) {
            $this->_month = sprintf("%02d", $config['month']);
            if (!in_array($this->_month, array('01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'))) {
                throw new Exception('Month value for Deliveries table is invalid.');
            }
            unset($config['month']);
        } else {
            throw new Exception('Month value for Deliveries table is required.');
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
        $sql = sprintf("
            CREATE TABLE IF NOT EXISTS `@table_name@` (
              `id` varbinary(20) NOT NULL,
              `start` datetime DEFAULT NULL,
			  `message_id` varbinary(20) NOT NULL,
			  `status` varchar(128) NOT NULL,
			  `type` varchar(128) NOT NULL,
			   `contact_id` varbinary(20) NOT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Created %s';",
                date('Y-m-d H:i:s')
        );

        return parent::_createTable($drop, $sql);
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
                    start BETWEEN '%s' AND '%s' OR
                )",
                    $sqlFrom, $sqlTo
            );
        }
	
        $sql = sprintf("
            SELECT IFNULL(id, ''),
                   IFNULL(start, '%s'),
                   IFNULL(message_id, ''),
                   IFNULL(status, ''),
                   IFNULL(type, ''),
                   IFNULL(contact_id, '')
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
		//print_r($db->getConnection());
		//exit();
		

        $db->getConnection()->exec($sql);

        // Prepend headers
        $headers = array(
            'id',
            'start',
            'message_id',
            'status',
            'type',
            'contact_id'
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
