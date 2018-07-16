<?php

namespace OP;
use SilverStripe\ORM\DataObject;


/**
 * Remembers the friendly name you set your field to. Allows you for example to
 * have a MemberID and present the name of the person to the CMS admin, instead 
 * of presenting them with an ugly ID
 */
class AutocompleteSuggestCache extends DataObject {

	private static $db = [
		'AutoField' => 'Varchar(33)',
		'AutoName' => 'Text',
	];
	private static $indexes = [
		'AutoField' => true
	];
	
	public static function find_or_create($autofield) {
		$cache = AutocompleteSuggestCache::get()->filter(array('AutoField' =>$autofield))->first();
		if ($cache) {
			return $cache;
		}
		return AutocompleteSuggestCache::create(array('AutoField' =>$autofield));
	}

}
