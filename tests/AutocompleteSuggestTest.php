<?php


use SilverStripe\Dev\SapphireTest;
use SilverStripe\View\Requirements;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Control\Controller;
use OP\AutocompleteSuggestField;

class AutocompleteSuggestTest extends SapphireTest implements TestOnly {

	public function testAutocompleteSuggestField() {
		$controller = new AutocompleteSuggestControllerTest();
		$testfield = AutocompleteSuggestField::create('MyField', $controller);

		// set value using an array
		$testfield->setValue('do-not-read', ['MyField' => ['id' => 'testid', 'name' => 'testvalue']]);
		$this->assertEquals(
				$testfield->dataValue(), 'testid', 'The test ID was not set correctly'
		);
		$this->assertEquals(
				$testfield->getDisplayName(), 'testvalue', 'The test value was not set correctly'
		);

		// set value like a normal field
		$normalfield = AutocompleteSuggestField::create('MyField', $controller);
		$normalfield->setValue('Boring value');
		$this->assertEquals(
				$normalfield->dataValue(), 'Boring value', 'The test id was not set correctly'
		);
		$this->assertEquals(
				$normalfield->getDisplayName(), null, 'The test value was not set correctly'
		);
	}

}


class AutocompleteSuggestControllerTest extends Controller implements TestOnly {

	private static $allowed_actions = array(
		'autocompleteMyField',
	);

	/**
	 * searches users in the local db
	 * @param SS_HTTPRequest $httprequest
	 */
	public function autocompleteMyField(HTTPRequest $httprequest) {
		Requirements::clear();
		return ['id' => 'test-id', 'name' => 'test-name'];
	}

}
