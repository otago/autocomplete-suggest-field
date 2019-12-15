<?php

namespace OP;

use SilverStripe\ORM\DataObject;


/**
 * Remembers the friendly name you set your field to. Allows you for example to
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
     * Either will create a new DataBase Object, or will return an existing pair
     * 
     * @param string $autofield cache key name
     * 
     * @return AutocompleteSuggestCache cache with a name-id pair
     */
    public static function findOrCreate($autofield)
    {
        $cache = AutocompleteSuggestCache::get()->filter(
            ['AutoField' => $autofield]
        )->first();
        if ($cache) {
            return $cache;
        }
        return AutocompleteSuggestCache::create(array('AutoField' => $autofield));
    }
}
