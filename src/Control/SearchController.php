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
        if (!singleton($request->getVar('ClassName'))->hasField('Title')) {
            throw new HTTPResponse_Exception($request->getVar('ClassName') . ' must have Title to search', 400);
        }
        $SearchedDataObject = Injector::inst()->create($request->getVar('ClassName'));

        $dbobjects = $SearchedDataObject::get()->filter(array('Title:StartsWith:nocase' => $request->getVar('query')))->limit(10);

        $returnarray = [];
        foreach ($dbobjects as $obj) {
            $returnarray[] = array('id' => $obj->ID, 'name' => $obj->getTitle());
        }

        print_r(json_encode($returnarray));
    }

    public function Link($action = null) {
        return 'otago-autocomplete-search';
    }

}
