<?php

/**
 * @property string $contact_id
 * @property string $contact_email
 * @property string $message_id
 * @property string $message_name
 * @property-write string $sent_at
 * @property-read int $sent_at
 * @property-write string $bounced_at
 * @property-read int $bounced_at
 * @property-write string $opened_at
 * @property-read int $opened_at
 * @property int $opened_count
 * @property-write string $clicked_at
 * @property-read int $clicked_at
 * @property int $clicked_count
 * @property-write string $added_at
 * @property-read int $added_at
 * @property-write string $modified_at
 * @property-read int $modified_at
 */
class DbTable_Row_Delivery extends Zend_Db_Table_Row_Abstract
{
    protected function _insert()
    {
        $this->added_at = new Zend_Db_Expr('NOW()');
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
            case 'start':
            case 'message_id':
            case 'status':
            case 'type':
            case 'contact_id':
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
            case 'start':
            case 'message_id':
            case 'status':
            case 'type':
            case 'contact_id':
                if (!empty($value) && !($value instanceOf Zend_Db_Expr)) {
                    $value = date('Y-m-d H:i:s', (is_int($value) ? $value : strtotime($value)));
                }
                break;
        }

        parent::__set($columnName, $value);
    }
}