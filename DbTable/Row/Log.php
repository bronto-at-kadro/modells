<?php

/**
 * @property-read int $id
 * @property string $activity_type
 * @property-read int $activity_from
 * @property-write string $activity_from
 * @property-read int $activity_to
 * @property-write string $activity_to
 * @property-read int $activity_last
 * @property-write string $activity_last
 * @property int $activity_total
 * @property string $message
 * @property-write string $started_at
 * @property-read int $started_at
 * @property-write string $modified_at
 * @property-read int $modified_at
 * @property-write string $finished_at
 * @property-read int $finished_at
 * @property-write string $export_started_at
 * @property-read int $export_started_at
 * @property-write string $export_finished_at
 * @property-read int $export_finished_at
 */
class DbTable_Row_Log extends Zend_Db_Table_Row_Abstract
{
    protected function _insert()
    {
        $this->started_at = new Zend_Db_Expr('NOW()');
    }

    protected function _update()
    {
        $this->modified_at = new Zend_Db_Expr('NOW()');
    }

    /**
     * Retrieve row field value
     *
     * @param  string $columnName The user-specified column name.
     * @return string             The corresponding column value.
     * @throws Zend_Db_Table_Row_Exception if the $columnName is not a column in the row.
     */
    public function __get($columnName)
    {
        $value = parent::__get($columnName);

        switch ($columnName) {
            case 'export_started_at':
            case 'export_finished_at':
            case 'started_at':
            case 'modified_at':
            case 'finished_at':
            case 'activity_from':
            case 'activity_to':
            case 'activity_last':
                if (!empty($value) && !($value instanceOf Zend_Db_Expr)) {
                    $value = is_int($value) ? $value : strtotime($value);
                }
                break;
        }

        return $value;
    }

    /**
     * Set row field value
     *
     * @param  string $columnName The column key.
     * @param  mixed  $value      The value for the property.
     * @return void
     * @throws Zend_Db_Table_Row_Exception
     */
    public function __set($columnName, $value)
    {
        switch ($columnName) {
            case 'export_started_at':
            case 'export_finished_at':
            case 'started_at':
            case 'modified_at':
            case 'finished_at':
            case 'activity_last':
                if (!empty($value) && !($value instanceOf Zend_Db_Expr)) {
                    $value = date('Y-m-d H:i:s', (is_int($value) ? $value : strtotime($value)));
                }
                break;
            case 'activity_from':
            case 'activity_to':
                if (!empty($value) && !($value instanceOf Zend_Db_Expr)) {
                    $value = date('Y-m-d', (is_int($value) ? $value : strtotime($value)));
                }
                break;
        }

        parent::__set($columnName, $value);
    }
}