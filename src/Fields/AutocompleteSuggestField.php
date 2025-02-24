<?php

/**
 * Uses the default dropdown field to create a searchable dropdown field via ajax
 *
 * @author torleif west <torleifw@op.ac.nz>
 */

namespace OP;

use Exception;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Dev\Debug;
use SilverStripe\Dev\Deprecation;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\SearchableDropdownField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\Security\SecurityToken;
use Closure;
use SilverStripe\Control\Controller;
use SilverStripe\Dev\Backtrace;
use SilverStripe\Forms\SearchableDropdownTrait;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\RelationList;
use SilverStripe\ORM\UnsavedRelationList;
use SilverStripe\Security\Member;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Requirements;
use SilverStripe\View\SSViewer;

class AutocompleteSuggestField extends SearchableDropdownField
{
    use SearchableDropdownTrait;
    private static array $allowed_actions = [
        'search',
    ];

    protected $searchCallback = null;

    // This needs to be defined on the class, not the trait, otherwise there is a PHP error
    protected $schemaComponent = 'SearchableDropdownField';

    /**
     * @param string        $name
     * @param string|null   $title
     * @param DataList|null $source
     * @param mixed         $value
     * @param string        $labelField
     * @return void
     */
    public function __construct(
        string $name,
        ?string $title,
        Closure $searchCallback,
        mixed $value = null,
        string $labelField = 'Title'
    ) {
        parent::__construct($name, $title, null, $value);
        $this->setValue($value);
        $this->sneedValue = $value;
        $this->setLabelField($labelField);
        $this->addExtraClass('ss-searchable-dropdown-field');

        $this->setCallBack($searchCallback);

        // we use this to trigger the lazy loading via ajax
        $this->setIsLazyLoaded(true);
        $this->setTemplate('AutocompleteSuggestField');

        // this inline js will add the label of the field to the value as json, so we can save it for later
        Requirements::customScript('
(function ($) {
	$(document).ready(function () {
        let itemid = $("div[name=\"' . $this->ID() . '\"], div[name=\"' . $this->ID() . '\[\]\"]");
        $(itemid).on("change",function () {

            // single dropdowns
            let labelvalue = $(itemid).find(".ss-searchable-dropdown-field__single-value").text();

            // multi value selects
            var labelvalues = $(itemid)
                .find(".ss-searchable-dropdown-field__multi-value__label").map(function() {
                return $(this).text();
            }).get();

            let hiddeninputs = $(this).siblings(".hidden-input-fields");
            let changeditem = $(this);
            // has_one or text input
            if(labelvalue) {
                hiddeninputs.html("");
                let hiddenval = $("<input/>");
                hiddenval.attr("name", "' . $this->ID() . '");
                hiddenval.attr("type", "hidden");

                let inputval = $("[name=\"AjaxShadow' . $this->ID() . '\"]");
                try {
                    let inputObj = JSON.parse(changeditem.val());
                    if (!inputObj.label) {
                        throw new Error("Label not found in input object");
                    }
                } catch (e) {
                    // set the input to be valid json
                    let jsone = JSON.stringify({label: labelvalue, value: inputval.val()});
                   hiddenval.val(jsone);
                   hiddeninputs.append(hiddenval);
                }
            }

            // many many values
            if(labelvalues.length > 0) {
                let inputfields = $("input[name=\'AjaxShadow' . $this->ID() . '[]\']");
                hiddeninputs.html("");
                for (let i = 0; i < inputfields.length; i++) {
                    try {
                        let inputObj = JSON.parse(inputfields.eq(i).val());
                        if (!inputObj.label) {
                            throw new Error("Label not found in input object");
                        }
                    } catch (e) {
                        // set the input to be valid json
                        let jsone = JSON.stringify({label: labelvalues[i], value: inputfields.eq(i).val()});
                        let hiddenval = $("<input/>");
                        hiddenval.attr("name", "' . $this->ID() . '\[\]");
                        hiddenval.attr("type", "hidden");
                        hiddenval.val(jsone);
                        hiddeninputs.append(hiddenval);
                    }
                }
            }
        }).trigger("change");
    });
})(jQuery);
        ');
    }


    /**
     * if it's a relationship - we get the value from the object. 
     * SS for some reason overrides the value with the object, so we need to get the value from the object
     */
    public function setValue($value, $data = null)
    {
        if (!$value && $data && is_object($data) && $data->hasMethod($this->getName())) {
            $name = $this->getName();
            if (
                !is_a($data->$name(), RelationList::class) ||
                !is_a($data->$name(), UnsavedRelationList::class)
            ) {
                return parent::setValue($data->$name());
            }
        }
        return parent::setValue($value, $data);
    }

    /**
     * @return array
     */
    public function getSchemaDataDefaults(): array
    {
        $data = parent::getSchemaDataDefaults();
        $data = $this->updateDataForSchema($data);
        $name = $this->getName();
        if ($this->isMultiple && strpos($name, '[') === false) {
            $name .= '[]';
        }
        $data['name'] = 'AjaxShadow' . $name;
        $data['disabled'] = $this->isDisabled() || $this->isReadonly();
        if ($this->getIsLazyLoaded()) {
            $data['optionUrl'] = Controller::join_links($this->Link(), 'search');
        } else {
            $data['options'] = array_values($this->getOptionsForSchema()->toNestedArray());
        }

        if ($this->Value()) {
            $caches = AutocompleteSuggestCache::find($this->ID() . $this->getName(), $this->Value());
            if (is_iterable($this->Value())) {
                // bypass the cache if it's a many_many
                foreach ($this->Value() as $value) {
                    if (is_object($value)) {
                        $data['value'][] = [
                            "value" =>  $value->ID,
                            "label" => $value->getTitle(),
                            "selected" => true
                        ];
                    } else {
                        $jsonobj = json_decode($value, true);
                        $data['value'][] = [
                            "value" =>  $jsonobj['value'],
                            "label" => $jsonobj['label'],
                            "selected" => true
                        ];
                    }
                }
            } else {
                if ($caches->first()) {
                    $data['value'] = [
                        "value" =>  $this->Value(),
                        "label" => $caches->first()->AutoName,
                        "selected" => true
                    ];
                } else {
                    $data['value'] = [
                        "value" =>  $this->Value(),
                        "label" => "Key: " . $this->value,
                        "selected" => true
                    ];
                }
            }
        } else {
            $data['value'] = null;
        }
        return $data;
    }


    /**
     * Search functions must return an array of associative arrays with 
     * 'value' and 'label' keys, e.g., [['value' => '1', 'label' => 'Item 1']]
     * @param Closure $searchCallback
     * @return void
     */
    public function setCallBack($searchCallback)
    {
        $this->searchCallback = $searchCallback;
        return $this;
    }
    /**
     * Returns a JSON string of options for lazy loading.
     * @param HTTPRequest $request
     * @return HTTPResponse
     */
    public function search(HTTPRequest $request): HTTPResponse
    {
        $response = HTTPResponse::create();
        $response->addHeader('Content-Type', 'application/json');
        if (!SecurityToken::singleton()->checkRequest($request)) {
            $response->setStatusCode(400);
            $response->setBody(json_encode(['message' => 'Invalid CSRF token']));
            return $response;
        }
        $term = $request->getVar('term') ?? '';

        $results = ($this->searchCallback)($term);

        // Ensure it's an array
        if (!is_array($results)) {
            throw new \InvalidArgumentException("Search function must return an array.");
        }

        // optional: ensure each result has a 'label' and 'value' key
        foreach ($results as $key => $result) {
            if (!isset($result['label']) || !isset($result['value'])) {
                $results[$key] = [
                    'label' => $result,
                    'value' => $key
                ];
            }
        }
        $results = array_values($results);

        $response->setBody(json_encode($results));
        return $response;
    }


    /**
     * @param DataObjectInterface $record
     * @return void
     */
    public function saveInto(DataObjectInterface $record): void
    {
        $name = $this->getName();

        // many many relationships don't need a label cache
        if (
            method_exists($record, 'hasMethod') &&
            $record->hasMethod($name) && (
                is_a($record->$name(), RelationList::class) ||
                is_a($record->$name(), UnsavedRelationList::class)
            )
        ) {
            $ids = [];
            foreach ($this->value() as $jsondata) {
                $decodedData = json_decode($jsondata, true);
                if (!isset($decodedData['value'])) {
                    throw new Exception("Value not found in input data");
                }
                $ids[] = (int) $decodedData['value'];
            }
            $relationList = $record->$name();
            $relationList->setByIDList($ids);
            return;
        }

        // decode and verify the json data
        $jsonvalue = json_decode($this->Value(), true);

        if (!is_string($this->Value()) || !array_key_exists('value', $jsonvalue) || !array_key_exists('label', $jsonvalue)) {
            throw new Exception("JSON data invalid");
        }
        $this->setValue($jsonvalue['value']);

        // clear the label cache, rewrite it
        AutocompleteSuggestCache::clearLabels($this->ID() . $this->getName(), $this->Value());
        AutocompleteSuggestCache::writecache($this->ID() . $this->getName(), $this->Value(), $jsonvalue['label']);

        // has_one field
        if (substr($name, -2) === 'ID') {
            // polymorphic has_one
            if (is_a($record, DataObject::class)) {
                // @var DataObject $record
                $classNameField = substr($name, 0, -2) . 'Class';
                if ($record->hasField($classNameField)) {
                    $record->$classNameField = $this->getValue() ? $record->ClassName : '';
                }
            }
        }
        FormField::saveInto($record);
    }

    /**
     * Set whether the field allows multiple values
     * This is only intended to be called from init() by implemented classes, and not called directly
     * To instantiate a dropdown where only a single value is allowed, use SearchableDropdownField.
     * To instantiate a dropdown where multiple values are allowed, use SearchableMultiDropdownField
     */
    public function setIsMultiple(bool $isMultiple): static
    {
        $this->isMultiple = $isMultiple;
        return $this;
    }

    /**
     * returns an array of fields that will be hidden in the template incase the javascript does not work
     * @return ArrayList
     */
    public function DataInputs()
    {
        $inputfields = $this->Value();
        $arrayList = new ArrayList();
        if (is_iterable($inputfields)) {
            foreach ($inputfields as $inputfield) {
                $arrayList->push(new ArrayData([
                    'Name' => $this->Name . '[]',
                    'Value' =>   json_encode(['label' => $inputfield->getTitle(), 'value' => $inputfield->ID]),
                ]));
            }
        } else {
            $cache = AutocompleteSuggestCache::find($this->ID() . $this->getName(), $this->Value());
            if ($cache->first()) {
                $arrayList->push(new ArrayData([
                    'Name' => $this->Name,
                    'Value' =>   json_encode(['label' => $cache->first()?->AutoName, 'value' => $this->Value()]),
                ]));
            } else {
                $arrayList->push(new ArrayData([
                    'Name' => $this->Name,
                    'Value' =>   json_encode(['label' => "Key: " . $this->Value(), 'value' =>  $this->value()]),
                ]));
            }
        }
        return $arrayList;
    }
}
