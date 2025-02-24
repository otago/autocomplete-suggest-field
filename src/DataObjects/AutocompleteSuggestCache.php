<?php

namespace OP;

use SilverStripe\ORM\DataObject;


/**
 * Remembers the friendly label of your field. Allows you for example to
 * have a MemberID and present the name of the person to the CMS admin, instead 
 * of presenting them with an ugly ID
 */
class AutocompleteSuggestCache extends DataObject
{

    private static $db = [
        'AutoField' => 'Varchar(255)',
        'AutoName' => 'Text',
    ];
    private static $indexes = [
        'AutoField' => true
    ];
    private static $table_name = 'AutocompleteSuggestCache';


    /**
     * finds any cache value for use in the field
     * 
     * @param string $fieldname string name of the field. ideally should be unique
     * @param mixed  $keyindex name of the index
     * 
     * @return DataList array of caches (can be multiple)
     */
    public static function find($fieldname, $keyindex)
    {
        $hashValue = md5((string)$keyindex);
        return AutocompleteSuggestCache::get()->filter(
            [
                'AutoField' => $fieldname . '_' . $hashValue
            ]
        );
    }

    /**
     * finds any cache value for use in the field
     * 
     * @param string $fieldname string name of the field. ideally should be unique
     * @param mixed  $keyindex name of the index
     * 
     * @return void
     */
    public static function clearLabels($fieldname, $keyindex)
    {
        $hashValue = md5((string)$keyindex);
        AutocompleteSuggestCache::get()->filter(
            [
                'AutoField' => $fieldname . '_' . $hashValue
            ]
        )->removeAll();
    }

    /**
     * finds any cache value for use in the field
     * 
     * @param string $fieldname cache key name
     * @param mixed  $value cache key name
     * 
     * @return AutocompleteSuggestCache
     */
    public static function writecache($fieldname, $keyindex, $label)
    {
        $hashValue = md5((string)$keyindex);
        $newCache = AutocompleteSuggestCache::create();
        $newCache->AutoField = $fieldname . '_' . $hashValue;
        $newCache->AutoName = $label;
        $newCache->write();
        return $newCache;
    }

    /**
     * gets the label for the cache
     * @return string
     */
    public function getLabel()
    {
        return $this->AutoName;
    }

    /**
     * we allow all CMS users to use the cache object
     * 
     * @param member $member
     * 
     * @return boolean
     */
    public function canView($member = null)
    {
        return true;
    }
}
