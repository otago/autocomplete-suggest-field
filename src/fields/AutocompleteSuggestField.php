<?php

namespace OP;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\Requirements;
use SilverStripe\Control\Controller;

/**
 * A generic and reusable ajax based auto complete suggest suggestion select box.
 * 
 * It enables you have a friendly suggest 
 */
class AutocompleteSuggestField extends TextField {

	protected $controller;
	protected $placeholder;
	protected $displayname;
	protected $dataobject;
	
	
    private static $casting = array(
        'AttributesDisplayHTML' => 'HTMLFragment'
    );

	/**
	 * Builds the autocomplete suggest field. includes the needed js and checks 
	 * to make sure you've got the allowed action public.
	 * @param string $name
	 * @param \Controller $controller
	 * @param string $title
	 * @param \Form $form
	 * @param type $dataojbect required if you need to make the friendly name unique against a page or other object
	 * @throws Exception
	 */
	function __construct($name, $controller, $title = null, $form = null, DataObject $dataojbect = null) {
		Requirements::javascript('autocomplete-suggest-field/javascript/AutocompleteSuggestField.js');

		$this->name = $name;
		$this->controller = $controller;
		$this->dataobject = $dataojbect;
		//$this->setTemplate('autocomplete-suggest-field/templates/AutocompleteSuggestField');

		if (!$this->controller->hasAction($this->getAutoCompleteActionName())) {
			throw new Exception('Controller ' . get_class($controller) .
			' must have an allowed_action called ' . $this->getAutoCompleteActionName() . ' for AutocompleteSuggestField');
		}

		$this->setPlaceholderText('Start typing to see more of ' . $name);
		$this->addExtraClass('datalistautocompletefield text');
		
		parent::__construct($name, $title, null, null, $form);
	}
	
	public function setDataObject (DataObject $dataobject) {
		$this->dataobject = $dataobject;
	}

	/**
	 * There are three types of data we can take,
	 *  1. some data from a data object
	 *  2. some data from an array
	 *  3. just some text which will be used for both the id and name value
	 * @param type $value
	 * @param DataObject $obj
	 * @return $this
	 */
	public function setValue($value, $obj = null) {
		$name = null;
		$id = null;
		
		if ($obj instanceof DataObject) {
			$name = $obj->getField($this->name);
			$id = $obj->getField($this->name);
        }
		
		// if we have been provided an array of values
		if (is_array($obj) ) {
			if ( array_key_exists($this->ID(), $obj)) {
				$name = $obj[$this->ID()]['name'];
				$id = $obj[$this->ID()]['id'];
			}
			
			$this->displayname = $name;
			$cache = AutocompleteSuggestCache::find_or_create($this->getCacheKey());
			$cache->AutoName = $name;
			if ($id != $name) {
				$cache->write();
			}
			
			$value = $id;
			
			// if no id has been provided, we just use the text the user entered
			if(!$id) {
				$value = $name;
			}
		}

		parent::setValue($value);

		return $this;
	}

	public function Value() {
		// you must have a dataobject to get the friendly name.
		if ($this->dataobject) {
			$cache = AutocompleteSuggestCache::find_or_create($this->getCacheKey());
			$this->displayname = $cache->AutoName;
		}
		return parent::Value();
	}

	public function getCacheKey() {
		$key = get_class($this->controller);
		if ($this->dataobject) {
			$key .= $this->dataobject->ClassName;
			$key .= '_'. $this->dataobject->ID;
		}
		return md5($key . $this->getName());
	}

	/**
	 * Returns the field value suitable for insertion into the data object.
	 *
	 * @return mixed
	 */
	public function dataValue() {
		if(is_array($this->value)) {
			return $this->value['name'];
		}
		return $this->value;
	}

	/**
	 * the name of the action that must live on the controller. it will be used
	 * to query the action
	 * 
	 * @return string
	 */
	public function getAutoCompleteActionName() {
		return 'autocomplete' . $this->getName();
	}

	public function getAutoCompleteURL() {
		return Controller::join_links($this->controller->Link(), $this->getAutoCompleteActionName());
	}

	public function getDataListName() {
		return 'DataList' . $this->ID();
	}

	public function getPlaceholderText() {
		return $this->placeholder;
	}

	public function setPlaceholderText($str) {
		$this->placeholder = $str;
	}

	public function getAttributes() {
		$attributes = array();

		$attributes['type'] = 'hidden';
		$attributes['name'] = $this->ID() . '[id]';
		$attributes['value'] = $this->dataValue();

		return array_merge(
				parent::getAttributes(), $attributes
		);
	}

	public function AttributesDisplayHTML() {
		$attributes = array();

		$attributes['list'] = $this->getDataListName();
		$attributes['autocomplete'] = 'off';
		$attributes['aria-autocomplete'] = $this->getDataListName();
		$attributes['placeholder'] = $this->getPlaceholderText();
		$attributes['data-url'] = $this->getAutoCompleteURL();
		$attributes['autofocus'] = 'autofocus';
		$attributes['type'] = 'text';
		$attributes['class'] = $this->extraClass();
		$attributes['disabled'] = $this->isDisabled();
		$attributes['readonly'] = $this->isReadonly();
		$attributes['data-populate-id'] = $this->ID() . '[id]';
		$attributes['name'] = $this->ID() . '[name]';
		$attributes['value'] = $this->displayname ?: $this->dataValue();

		return $this->getAttributesHTML($attributes);
	}

}
