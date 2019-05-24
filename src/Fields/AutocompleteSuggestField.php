<?php
/**
 * AutocompleteSuggestField - used if a dropdown field gets too large
 */
namespace OP;

use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\Requirements;
use SilverStripe\Control\Controller;
use Exception;

/**
 * A generic and reusable ajax based auto complete suggest suggestion select box.
 * It enables you have a friendly suggest box in the CMS or front end.
 * 
 * @category Field
 * @package  AutocompleteSuggestField
 * @author   Torleif West <torleifw@op.ac.nz>
 */
class AutocompleteSuggestField extends TextField
{

    protected $controller;
    protected $placeholder;
    protected $displayname;
    protected $dataobject;
    protected $parent;

    private static $casting = array(
        'AttributesDisplayHTML' => 'HTMLFragment'
    );

    /**
     * Builds the autocomplete suggest field. includes the needed js and checks 
     * to make sure you've got the allowed action public.
     *
     * @param string      $name       name of the field
     * @param \DataObject $parent     used to fetch the text used 
     *                                instead of the ID
     * @param string      $title      human readable name of the form
     * @param Contoller   $controller if you want to customise search
     * @param \Form       $form       the form this field is embeeded in
     * 
     * @throws Exception
     */
    function __construct(
        $name,
        $parent,
        $title = null,
        Controller $controller = null,
        Form $form = null
    ) {
        Requirements::javascript(
            'otago/autocomplete-suggest-field:' .
                ' javascript/AutocompleteSuggestField.js'
        );

        $this->name = $name;
        $this->parent = $parent;
        $this->controller = $controller;

        if (!$this->parent instanceof DataObject) {
            throw new Exception(
                'parent must be DataObject'
            );
        }

        if ($this->controller && !$this->controller->hasAction($this->getAutoCompleteActionName())) {
            throw new Exception(
                'Controller ' . get_class($controller) .
                    ' must have an allowed_action called ' . $this->getAutoCompleteActionName() .
                    ' for AutocompleteSuggestField'
            );
        }


        $this->setPlaceholderText('Start typing to see more of ' . $name);
        $this->addExtraClass('datalistautocompletefield text');

        parent::__construct($name, $title, null, null, $form);
    }

    /**
     * Sets the foreign relationship dataobject
     * 
     * @param DataObject $dataobject dataobject in question
     * 
     * @return void
     */
    public function setDataObject(DataObject $dataobject)
    {
        $this->dataobject = $dataobject;
    }

    /**
     * There are three types of data we can take,
     *  1. some data from an array
     *  2. just some text which will be used for both the id and name value
     * 
     * @param type  $value value to set
     * @param array $obj   additiaonal information about how it was set
     * 
     * @return \AutocompleteSuggestField for chaining
     */
    public function setValue($value, $obj = null)
    {
        $name = null;
        $id = null;

        // if we have been provided an array of values
        if (is_array($obj)) {
            if (array_key_exists($this->ID(), $obj)) {
                $name = $obj[$this->ID()]['name'];
                $id = $obj[$this->ID()]['id'];
            }

            $this->tmpid = $id;
            $this->displayname = $name;
            $cache = AutocompleteSuggestCache::findOrCreate($this->getCacheKey());
            $cache->AutoName = $name;
            if ($this->parent->ID) {
                $cache->write();
            }

            $value = $id;

            // if no id has been provided, we just use the text the user entered
            if (!$id) {
                $value = $name;
            }
        }
        parent::setValue($value);

        return $this;
    }

    /**
     * Returns the ID value
     * 
     * @return int
     */
    public function Value()
    {
        // you must have a dataobject to get the friendly name.
        $cache = AutocompleteSuggestCache::findOrCreate($this->getCacheKey());
        $this->displayname = $cache->AutoName;
        if (!$this->displayname) {
            $classname = $this->getTargetClassName();
            
            if (!class_exists($classname)) {
                return parent::Value();
            }
            $dataobject = $classname::get()->byid(parent::Value());
            if ($dataobject) {
                $this->displayname = $dataobject->Title;
            }
        }

        return parent::Value();
    }

    /**
     * Returns the user friendly text name (e.g. John Smith)
     * 
     * @return string
     */
    public function getDisplayName()
    {
        return $this->displayname;
    }

    /**
     * Returns a unqiue cache string to identify this field
     * 
     * @return string a unqie string used for key
     */
    public function getCacheKey()
    {
        $key = $this->parent->RecordClassName . '_';
        $key .= $this->parent->ID . '_';
        $key .= $this->name;

        return $key;
    }

    /**
     * Returns the field value suitable for insertion into the data object.
     *
     * @return mixed
     */
    public function dataValue()
    {
        if (is_array($this->value)) {
            return $this->value['name'];
        }
        return $this->value;
    }

    /**
     * The name of the action that must live on the controller. it will be used
     * to query the action
     * 
     * @return string
     */
    public function getAutoCompleteActionName()
    {
        return 'autocomplete' . $this->getName();
    }

    /**
     * Returns a URL for ajax
     * 
     * @return string to ajax in search results
     */
    public function getAutoCompleteURL()
    {
        if (!$this->controller) {
            return Controller::join_links(
                singleton(SearchController::class)->Link()
            );
        }
        return Controller::join_links(
            $this->controller->Link(),
            $this->getAutoCompleteActionName()
        );
    }

    public function getDataListName()
    {
        return 'DataList' . $this->ID();
    }

    public function getPlaceholderText()
    {
        return $this->placeholder;
    }

    public function setPlaceholderText($str)
    {
        $this->placeholder = $str;
    }

    public function getAttributes()
    {
        $attributes = array();

        $attributes['type'] = 'hidden';
        $attributes['name'] = $this->ID() . '[id]';
        $attributes['value'] = $this->dataValue();

        return array_merge(
            parent::getAttributes(),
            $attributes
        );
    }

    /**
     * Returns a string of the fully qualified classname used
     * e.g. SilverStripe\Security\Member
     * 
     * @return string classname
     */
    public function getTargetClassName()
    {
        $idless = preg_replace('/ID$/m', '', $this->name);
        return $this->parent->getRelationClass($this->name) ?: $this->parent->getRelationClass($idless);
    }

    /**
     * Creates paramaters used to build the input field for ajax search
     * 
     * @return array a list of all the things needed to create an ajax query
     */
    public function AttributesDisplayHTML()
    {
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
        $attributes['data-classname'] = $this->getTargetClassName();

        return $this->getAttributesHTML($attributes);
    }
}
