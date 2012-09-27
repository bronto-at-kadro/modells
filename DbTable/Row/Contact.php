<?php

/**
 * @property string $contact_id
 * @property string $contact_email
 */
class DbTable_Row_Contact extends Zend_Db_Table_Row_Abstract
{
    /**
     * @return boolean
     */
    public function hasEmail()
    {
        if ($this->contact_email && !empty($this->contact_email)) {
            return true;
        }

        return false;
    }
}