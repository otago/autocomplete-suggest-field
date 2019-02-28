<?php

namespace OP;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Security\Security;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Core\Injector\Injector;

class SearchController extends Controller {

    private static $allowed_actions = array(
        'index',
        'getAutoCompleteActionName'
    );

    public function index(HTTPRequest $request) {
        $this->getAutoCompleteActionName($request);
    }

    /**
     * default search query for dataobjects
     * @param HTTPRequest $request
     * @throws HTTPResponse_Exception
     */
    public function getAutoCompleteActionName(HTTPRequest $request) {
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
     * return a query that will return results of the search. Will look at $searchable_fields
     * of the dataobject. If none are found, it will resort to Title. Title must exist on the dataobject
     * 
     * @param HTTPRequest $request
     * @return \DataList
     * @throws HTTPResponse_Exception
     */
    public function buildsearchquery(HTTPRequest $request) {
        $SearchedDataObject = Injector::inst()->create($request->getVar('ClassName'));

        $searchfields = $SearchedDataObject->config()->get('searchable_fields');
        if ($searchfields) {
            $searchquery = [];
            foreach ($searchfields as $field) {
                $searchquery[$field . ':StartsWith:nocase'] = $request->getVar('query');
            }

            return $SearchedDataObject::get()->filterAny($searchquery)->limit(10);
        }

        if (!singleton($request->getVar('ClassName'))->hasField('Title')) {
            throw new HTTPResponse_Exception($request->getVar('ClassName') . ' must have Title to search', 400);
        }

        return $SearchedDataObject::get()->filter(array('Title:StartsWith:nocase' => $request->getVar('query')))->limit(10);
    }

    public function Link($action = null) {
        return 'otago-autocomplete-search';
    }

}
