<?php

namespace OP;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Security\Security;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Security\Member;


class SearchController extends Controller
{

    private static $allowed_actions = array(
        'index',
        'getAutoCompleteActionName'
    );

    /**
     * Default action on controller
     * 
     * @param HTTPRequest $request HTML request
     * 
     * @return void
     */
    public function index(HTTPRequest $request)
    {
        $this->getAutoCompleteActionName($request);
    }

    /**
     * Default search query for dataobjects
     *
     * @param HTTPRequest $request HTML request made 
     * 
     * @throws HTTPResponse_Exception
     * 
     * @return void
     */
    public function getAutoCompleteActionName(HTTPRequest $request)
    {
        if (!Security::getCurrentUser()) {
            throw new HTTPResponse_Exception('You must be signed in', 400);
        }
        if (!$request->getVar('ClassName')) {
            throw new HTTPResponse_Exception('ClassName not found', 400);
        }
        if (!singleton($request->getVar('ClassName'))->canView()) {
            throw new HTTPResponse_Exception('You do not have permission to view this DataObject', 400);
        }
        if (!$request->getVar('query')) {
            throw new HTTPResponse_Exception('Query not found', 400);
        }
        if (strlen($request->getVar('query')) < 1) {
            throw new HTTPResponse_Exception('Query not long enough', 400);
        }

        $dbobjects = $this->buildsearchquery($request);

        $returnarray = [];
        foreach ($dbobjects as $obj) {
            $returnarray[] = array('id' => $obj->ID, 'name' => $obj->getTitle());
        }

        print_r(json_encode($returnarray));
    }

    /**
     * Return a query that will return results of the search. Will look at
     *  $searchable_fields of the dataobject. If none are found, it will
     *  resort to Title. Title must exist on the dataobject
     * 
     * @param HTTPRequest $request html request of the current controller
     * 
     * @return \DataList
     * 
     * @throws HTTPResponse_Exception
     */
    public function buildsearchquery(HTTPRequest $request)
    {
        $SearchedDataObject = Injector::inst()->create($request->getVar('ClassName'));

        // check for a first-name last-name pattern with people's names
        if ($SearchedDataObject->ClassName === Member::class) {
            $searchvalues = explode(' ', $request->getVar('query'));
            if (count($searchvalues) === 2) {
                list($firstname, $lastname) = $searchvalues;

                $searchquery['FirstName:StartsWith:nocase'] = $firstname;
                $searchquery['Surname:StartsWith:nocase'] = $lastname;
                return $SearchedDataObject::get()->filter($searchquery)->limit(10);
            }
        }

        if ($fieldSpecs = $SearchedDataObject->searchableFields()) {
            $customSearchableFields = $SearchedDataObject->config()->get('searchable_fields');
            $searchquery = [];
            foreach ($fieldSpecs as $name => $spec) {
                if (is_array($spec) && array_key_exists('filter', $spec ?? [])) {
                    // The searchableFields() spec defaults to PartialMatch,
                    // so we need to check the original setting.
                    // If the field is defined $searchable_fields = array('MyField'),
                    // then default to StartsWith filter, which makes more sense in this context.
                    if (!$customSearchableFields || array_search($name, $customSearchableFields ?? []) !== false) {
                        $filter = 'StartsWith:nocase';
                    } else {
                        $filterName = $spec['filter'];
                        // It can be an instance
                        if ($filterName instanceof SearchFilter) {
                            $filterName = get_class($filterName);
                        }
                        // It can be a fully qualified class name
                        if (strpos($filterName ?? '', '\\') !== false) {
                            $filterNameParts = explode("\\", $filterName ?? '');
                            // We expect an alias matching the class name without namespace, see #coresearchaliases
                            $filterName = array_pop($filterNameParts);
                        }
                        $filter = preg_replace('/Filter$/', '', $filterName ?? '');
                    }
                    $field = "{$name}:{$filter}";
                } else {
                    $field = $name . ':StartsWith:nocase';
                }

                $searchquery[$field] = $request->getVar('query');
            }

            return $SearchedDataObject::get()->filterAny($searchquery)->limit(10);
        }

        if (!singleton($request->getVar('ClassName'))->hasField('Title')) {
            throw new HTTPResponse_Exception($request->getVar('ClassName') . ' must have Title to search', 400);
        }

        return $SearchedDataObject::get()->filter(
            ['Title:StartsWith:nocase' => $request->getVar('query')]
        )->limit(10);
    }

    /**
     * Returns a string url to the current controller
     * 
     * @param string $action ignored. used to match SS API
     * 
     * @return string the link
     */
    public function Link($action = null)
    {
        return 'otago-autocomplete-search';
    }
}
