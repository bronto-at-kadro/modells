<?php

/**
 * @method DbTable_Row_Log createRow() createRow(array $data)
 */
class DbTable_Logs extends DbTable_Abstract
{
    protected $_primary  = 'id';
    protected $_name     = 'logs';
    protected $_rowClass = 'DbTable_Row_Log';

    /**
     * @return DbTable_Row_Log
	 * getLastSuccessfulRun($type) -> getLastSuccessfulActivityRun($type)
     */
    public function getLastSuccessfulActivityRun($type)
    {
        $select = $this->select();
        $select->from($this, array('activity_from', 'activity_to', 'activity_last'));
        $select->where('activity_type = ?', $type);
        $select->where('export_finished_at IS NOT NULL');
        $select->where('message IS NULL');
        $select->order('activity_to DESC');
        $select->limit(1);

        return $this->fetchRow($select);
    }
	
	/**
     * @return DbTable_Row_Log
     */
    public function getLastSuccessfulDeliveryRun($type)
    {
        $select = $this->select();
        $select->from($this, array('delivery_from', 'delivery_to', 'delivery_last'));
        $select->where('delivery_type = ?', $type);
        $select->where('export_finished_at IS NOT NULL');
        $select->where('message IS NULL');
        $select->order('delivery_to DESC');
        $select->limit(1);

        return $this->fetchRow($select);
    }

    /**
     * @return DbTable_Row_Log
	 * getRun() -> getActivitiesRun()
     */
    public function getActivityRun($type, $from)
    {
        $select = $this->select();
        $select->where('activity_type = ?', $type);
        $select->where('activity_from = ?', date('Y-m-d', $from));
        $select->limit(1);

        return $this->fetchRow($select);
    }
	
	 /**
     * @return DbTable_Row_Log
     */
    public function getDeliveryRun($type, $from)
    {
        $select = $this->select();
        $select->where('delivery_type = ?', $type);
        $select->where('delivery_from = ?', date('Y-m-d', $from));
        $select->limit(1);

        return $this->fetchRow($select);
    }

    /**
     * @return DbTable_Row_Log
	 * getUnfinishedRuns() -> getUnfinishedActivityRuns()
     */
    public function getUnfinishedActivityRuns()
    {
        $select = $this->select();
        $select->from($this, array('activity_from', 'activity_to', new Zend_Db_Expr('COUNT(*)')));
        $select->group(array('activity_from', 'activity_to'));
        $select->having('COUNT(*) < 4');
        $select->limit(1);

        if ($row = $this->fetchRow($select)) {
            $select = $this->select();
            $select->from($this, array('activity_type'));
            $select->where('activity_from = ?', date('Y-m-d', $row->activity_from));
            $select->where('activity_to = ?', date('Y-m-d', $row->activity_to));
            if ($rowset = $this->fetchAll($select)) {
                $finished = array();
                foreach ($rowset as $finishedRow) {
                    $finished[] = $finishedRow->activity_type;
                }
                $diff = array_diff(array(
                    Bronto_Api_Activity::TYPE_SEND,
                    Bronto_Api_Activity::TYPE_OPEN,
                    Bronto_Api_Activity::TYPE_CLICK,
                    Bronto_Api_Activity::TYPE_BOUNCE,
                ), $finished);
                if (!empty($diff)) {
                    return array(
                        'activity_from'    => $row->activity_from,
                        'activity_to'      => $row->activity_to,
                        'unfinished_types' => $diff,
                    );
                }
            }
        }

        return false;
    }


	/**
     * @return DbTable_Row_Log
     */
    public function getUnfinishedDeliveryRuns()
    {
        $select = $this->select();
        $select->from($this, array('activity_from', 'activity_to', new Zend_Db_Expr('COUNT(*)')));
        $select->group(array('activity_from', 'activity_to'));
        $select->having('COUNT(*) < 4');
        $select->limit(1);

        if ($row = $this->fetchRow($select)) {
            $select = $this->select();
            $select->from($this, array('activity_type'));
            $select->where('activity_from = ?', date('Y-m-d', $row->activity_from));
            $select->where('activity_to = ?', date('Y-m-d', $row->activity_to));
            if ($rowset = $this->fetchAll($select)) {
                $finished = array();
                foreach ($rowset as $finishedRow) {
                    $finished[] = $finishedRow->activity_type;
                }
                $diff = array_diff(array(
                    Bronto_Api_Activity::TYPE_SEND,
                    Bronto_Api_Activity::TYPE_OPEN,
                    Bronto_Api_Activity::TYPE_CLICK,
                    Bronto_Api_Activity::TYPE_BOUNCE,
                ), $finished);
                if (!empty($diff)) {
                    return array(
                        'activity_from'    => $row->activity_from,
                        'activity_to'      => $row->activity_to,
                        'unfinished_types' => $diff,
                    );
                }
            }
        }

        return false;
    }


    /**
     * @return DbTable_Row_Log
	 * getNextExport() -> getNextActivityExport();
     */
    public function getNextActivityExport()
    {
        $select = $this->select();
        $select->from($this, array('activity_from', 'activity_to', new Zend_Db_Expr('COUNT(*)')));
        $select->where('export_started_at IS NULL');
        $select->where('message IS NULL');
        $select->order('activity_from ASC');
        $select->group(array('activity_from', 'activity_to'));
        $select->having('COUNT(*) = 4');
        $select->limit(1);

        return $this->fetchRow($select);
    }
	
	
	/**
     * @return DbTable_Row_Log
     */
    public function getNextDeliveryExport()
    {
        $select = $this->select();
        $select->from($this, array('delivery_from', 'delivery_to', new Zend_Db_Expr('COUNT(*)')));
        $select->where('export_started_at IS NULL');
        $select->where('message IS NULL');
        $select->order('delivery_from ASC');
        $select->group(array('delivery_from', 'delivery_to'));
        $select->having('COUNT(*) = 4');
        $select->limit(1);

        return $this->fetchRow($select);
    }

    /**
     * @param DbTable_Row_Log $row
     * @return mixed
	 * markExport() -> markActivityExport()
     */
    public function markActivityExport(DbTable_Row_Log $row, $type = 'started')
    {
        if ($type === 'started') {
            return $this->update(array(
                'export_started_at' => new Zend_Db_Expr('NOW()'),
            ), array(
                $this->getAdapter()->quoteInto('activity_from = ?', date('Y-m-d', $row->activity_from)),
                $this->getAdapter()->quoteInto('activity_to = ?',   date('Y-m-d', $row->activity_to)),
            ));
        } elseif ($type === 'finished') {
            return $this->update(array(
                'export_finished_at' => new Zend_Db_Expr('NOW()'),
            ), array(
                $this->getAdapter()->quoteInto('activity_from = ?', date('Y-m-d', $row->activity_from)),
                $this->getAdapter()->quoteInto('activity_to = ?',   date('Y-m-d', $row->activity_to)),
            ));
        }
    }
	
	 /**
     * @param DbTable_Row_Log $row
     * @return mixed
     */
    public function markDeliveryExport(DbTable_Row_Log $row, $type = 'started')
    {
        if ($type === 'started') {
            return $this->update(array(
                'export_started_at' => new Zend_Db_Expr('NOW()'),
            ), array(
                $this->getAdapter()->quoteInto('delivery_from = ?', date('Y-m-d', $row->delivery_from)),
                $this->getAdapter()->quoteInto('delivery_to = ?',   date('Y-m-d', $row->delivery_to)),
            ));
        } elseif ($type === 'finished') {
            return $this->update(array(
                'export_finished_at' => new Zend_Db_Expr('NOW()'),
            ), array(
                $this->getAdapter()->quoteInto('delivery_from = ?', date('Y-m-d', $row->delivery_from)),
                $this->getAdapter()->quoteInto('delivery_to = ?',   date('Y-m-d', $row->delivery_to)),
            ));
        }
    }

    /**
     * @return string
	 * getLastFilterDate() -> getLastActivityFilterDate()
     */
    public function getLastActivityFilterDate($type)
    {
        $select = $this->select();
        $select->where('activity_type = ?', $type);
        $select->where('activity_last IS NOT NULL');
        $select->order('id DESC');
        $select->limit(1);

        if ($row = $this->fetchRow($select)) {
            return date('c', $row->activity_last);
        }

        return date('c', strtotime('2002-01-01 00:00:00'));
    }
	
	 /**
     * @return string
     */
    public function getLastDeliveryFilterDate($type)
    {
        $select = $this->select();
        $select->where('delivery_type = ?', $type);
        $select->where('delivery_last IS NOT NULL');
        $select->order('id DESC');
        $select->limit(1);

        if ($row = $this->fetchRow($select)) {
            return date('c', $row->delivery_last);
        }

        return date('c', strtotime('2002-01-01 00:00:00'));
    }

    /**
     * Creates an instance of this table dynamically
     *
     * @param bool $drop
     */
    public function createTable($drop = false)
    {
	/*
        $sql = "
            CREATE TABLE IF NOT EXISTS `@table_name@` (
              `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
              `activity_type` enum('send','open','view','click','conversion','unsubscribe','bounce') NOT NULL,
              `activity_from` date NOT NULL,
              `activity_to` date DEFAULT NULL,
              `activity_last` datetime DEFAULT NULL,
              `activity_total` int(10) unsigned NOT NULL DEFAULT '0',
			  `delivery_type` enum('forwardtoafriend') NOT NULL,
              `delivery_from` date NOT NULL,
              `delivery_to` date DEFAULT NULL,
              `delivery_last` datetime DEFAULT NULL,
              `delivery_total` int(10) unsigned NOT NULL DEFAULT '0',
              `message` varchar(255) DEFAULT NULL,
              `started_at` datetime DEFAULT NULL,
              `modified_at` datetime DEFAULT NULL,
              `finished_at` datetime DEFAULT NULL,
              `export_started_at` datetime DEFAULT NULL,
              `export_finished_at` datetime DEFAULT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `UX_run` (`activity_type`,`activity_from`) USING BTREE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ";
		*/
		 $sql = "
            CREATE TABLE IF NOT EXISTS `@table_name@` (
              `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
              `activity_type` enum('send','open','view','click','conversion','unsubscribe','bounce', 'forwardtoafriend') NOT NULL,
              `activity_from` date NOT NULL,
              `activity_to` date DEFAULT NULL,
              `activity_last` datetime DEFAULT NULL,
              `activity_total` int(10) unsigned NOT NULL DEFAULT '0',
			  `delivery_type` enum('forwardtoafriend') NULL,
              `delivery_from` date NOT NULL,
              `delivery_to` date DEFAULT NULL,
              `delivery_last` datetime DEFAULT NULL,
              `delivery_total` int(10) unsigned NOT NULL DEFAULT '0',
              `message` varchar(255) DEFAULT NULL,
              `started_at` datetime DEFAULT NULL,
              `modified_at` datetime DEFAULT NULL,
              `finished_at` datetime DEFAULT NULL,
              `export_started_at` datetime DEFAULT NULL,
              `export_finished_at` datetime DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ";

        return parent::_createTable($drop, $sql);
    }
}