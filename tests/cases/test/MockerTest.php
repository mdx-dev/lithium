<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2012, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace lithium\tests\cases\test;

use lithium\test\Mocker;

/**
 * WARNING:
 * No unit test should mock the same test as another to avoid conflicting filters.
 */
class MockerTest extends \lithium\test\Unit {

	public function setUp() {
		Mocker::register();
	}

	public function testAutoloadRegister() {
		Mocker::register();
		$registered = spl_autoload_functions();
		$this->assertTrue(in_array(array(
			'lithium\test\Mocker',
			'create'
		), $registered));
	}

	public function testBasicCreation() {
		$mockee = 'lithium\console\command\Mock';
		Mocker::create($mockee);
		$this->assertTrue(class_exists($mockee));
	}

	public function testBasicCreationExtendsCorrectParent() {
		$mocker = 'lithium\console\Request';
		$mockeeObj = new \lithium\console\request\Mock();
		$this->assertTrue(is_a($mockeeObj, $mocker));
	}

	public function testCanMockNonLithiumClasses() {
		$mockee = 'stdClass\Mock';
		Mocker::create($mockee);
		$this->assertTrue(class_exists($mockee));
	}

	public function testNonLithiumInstanceClass() {
		$std = new \lithium\tests\mocks\test\mockNonLi3StdClass\Mock;

		$this->assertInternalType('bool', $std->method1());

		$std->applyFilter('method1', function($self, $params, $chain) {
			return array();
		});

		$this->assertInternalType('array', $std->method1());
	}

	public function testNonLithiumStaticClass() {
		$class = 'lithium\analysis\debugger\Mock';
		$var = array('foo', 'bar', 'baz');

		$this->assertInternalType('string', $class::export($var));

		$class::applyFilter('export', function($self, $params, $chain) {
			return array();
		});

		$this->assertInternalType('array', $class::export($var));
	}

	public function testCannotCreateNonStandardMockClass() {
		$mockee = 'lithium\console\request\Mocker';
		Mocker::create($mockee);
		$this->assertTrue(!class_exists($mockee));
	}

	public function testFilteringNonStaticClass() {
		$dispatcher = new \lithium\console\dispatcher\Mock();

		$originalResult = $dispatcher->config(array());

		$dispatcher->applyFilter('config', function($self, $params, $chain) {
			return array();
		});

		$filteredResult = $dispatcher->config(array());

		$this->assertEqual(0, count($filteredResult));
		$this->assertNotEqual($filteredResult, $originalResult);
	}

	public function testFilteringNonStaticClassCanReturnOriginal() {
		$response = new \lithium\console\response\Mock();

		$originalResult = $response->styles();

		$response->applyFilter('styles', function($self, $params, $chain) {
			return $chain->next($self, $params, $chain);
		});

		$filteredResult = $response->styles();

		$this->assertEqual($filteredResult, $originalResult);
	}

	public function testFilteringStaticClass() {
		$mockee = 'lithium\analysis\parser\Mock';

		$code = 'echo "foobar";';

		$originalResult = $mockee::tokenize($code, array('wrap' => true));

		$mockee::applyFilter('tokenize', function($self, $params, $chain) {
			return array();
		});

		$filteredResult = $mockee::tokenize($code, array('wrap' => true));

		$this->assertEqual(0, count($filteredResult));
		$this->assertNotEqual($filteredResult, $originalResult);
	}

	public function testFilteringStaticClassCanReturnOriginal() {
		$mockee = 'lithium\analysis\inspector\Mock';

		$originalResult = $mockee::methods('lithium\analysis\Inspector');

		$mockee::applyFilter('tokenize', function($self, $params, $chain) {
			return $chain->next($self, $params, $chain);
		});

		$filteredResult = $mockee::methods('lithium\analysis\Inspector');

		$this->assertEqual($filteredResult, $originalResult);
	}

	public function testOriginalMethodNotCalled() {
		$http = new \lithium\tests\mocks\security\auth\adapter\mockHttp\Mock;

		$this->assertEqual(0, count($http->headers));

		$http->_writeHeader('Content-type: text/html');

		$this->assertEqual(1, count($http->headers));

		$http->applyFilter('_writeHeader', function($self, $params, $chain) {
			return false;
		});

		$http->_writeHeader('Content-type: application/pdf');

		$this->assertEqual(1, count($http->headers));
	}

	public function testFilteringAFilteredMethod() {
		$adapt = 'lithium\core\adaptable\Mock';
		$adapt::applyFilter('_initAdapter', function($self, $params, $chain) {
			return false;
		});
		$this->assertIdentical(false, $adapt::_initAdapter('foo', array()));
	}

	public function testStaticResults() {
		$docblock = 'lithium\analysis\docblock\Mock';
		$docblock::applyFilter(array('comment', 'tags'), function($self, $params, $chain) {
			return false;
		});
		$docblock::comment('foobar');
		$docblock::comment('bar');
		$docblock::tags('baz', 'foo');

		$this->assertIdentical(2, count($docblock::$staticResults['comment']));
		$this->assertIdentical(array('foobar'), $docblock::$staticResults['comment'][0]['args']);
		$this->assertIdentical(false, $docblock::$staticResults['comment'][0]['result']);
		$this->assertIdentical(array('bar'), $docblock::$staticResults['comment'][1]['args']);
		$this->assertIdentical(false, $docblock::$staticResults['comment'][1]['result']);

		$this->assertIdentical(1, count($docblock::$staticResults['tags']));
		$this->assertIdentical(array('baz', 'foo'), $docblock::$staticResults['tags'][0]['args']);
		$this->assertIdentical(false, $docblock::$staticResults['tags'][0]['result']);
	}

	public function testInstanceResults() {
		$debugger = new \lithium\data\schema\Mock;
		$debugger->applyFilter(array('names', 'meta'), function($self, $params, $chain) {
			return false;
		});
		$debugger->names('foo', 'foobar');
		$debugger->names('bar');
		$debugger->meta('baz');

		$this->assertIdentical(2, count($debugger->results['names']));
		$this->assertIdentical(array('foo', 'foobar'), $debugger->results['names'][0]['args']);
		$this->assertIdentical(false, $debugger->results['names'][0]['result']);
		$this->assertIdentical(array('bar'), $debugger->results['names'][1]['args']);
		$this->assertIdentical(false, $debugger->results['names'][1]['result']);

		$this->assertIdentical(1, count($debugger->results['meta']));
		$this->assertIdentical(array('baz'), $debugger->results['meta'][0]['args']);
		$this->assertIdentical(false, $debugger->results['meta'][0]['result']);
	}

	public function testSkipByReference() {
		$stdObj = new \lithium\tests\mocks\test\mockStdClass\Mock();
		$stdObj->foo = 'foo';
		$originalData = $stdObj->data();
		$stdObj->applyFilter('data', function($self, $params, $chain) {
			return array();
		});
		$nonfilteredData = $stdObj->data();
		$this->assertIdentical($originalData, $nonfilteredData);
	}

	public function testGetByReference() {
		$stdObj = new \lithium\tests\mocks\test\mockStdClass\Mock();
		$stdObj->foo = 'foo';
		$foo =& $stdObj->foo;
		$foo = 'bar';
		$this->assertIdentical('bar', $stdObj->foo);
	}

	public function testChainReturnsMockerChain() {
		$this->assertTrue(Mocker::chain(new \stdClass) instanceof \lithium\test\MockerChain);
	}

	public function testMergeWithEmptyArray() {
		$results = array();
		$staticResults = array(
			'method1' => array(
				array(
					'args' => array(),
					'results' => true,
					'time' => 100,
				),
			),
		);

		$this->assertEqual($staticResults, Mocker::mergeResults($results, $staticResults));
		$this->assertEqual($staticResults, Mocker::mergeResults($staticResults, $results));
	}

	public function testMultipleResultsSimple() {
		$results = array(
			'method1' => array(
				array(
					'args' => array(),
					'results' => true,
					'time' => 100,
				),
			),
		);
		$staticResults = array(
			'method1' => array(
				array(
					'args' => array(),
					'results' => true,
					'time' => 0,
				),
			),
		);
		$expected = array(
			'method1' => array(
				array(
					'args' => array(),
					'results' => true,
					'time' => 0,
				),
				array(
					'args' => array(),
					'results' => true,
					'time' => 100,
				),
			),
		);
		$this->assertEqual($expected, Mocker::mergeResults($results, $staticResults));
	}

	public function testMultipleResultsComplex() {
		$results = array(
			'method1' => array(
				array(
					'args' => array(),
					'results' => true,
					'time' => 100,
				),
			),
			'method2' => array(
				array(
					'args' => array(),
					'results' => true,
					'time' => 100,
				),
			),
		);
		$staticResults = array(
			'method1' => array(
				array(
					'args' => array(),
					'results' => true,
					'time' => 0,
				),
				array(
					'args' => array(),
					'results' => true,
					'time' => 200,
				),
			),
		);
		$expected = array(
			'method1' => array(
				array(
					'args' => array(),
					'results' => true,
					'time' => 0,
				),
				array(
					'args' => array(),
					'results' => true,
					'time' => 100,
				),
				array(
					'args' => array(),
					'results' => true,
					'time' => 200,
				),
			),
			'method2' => array(
				array(
					'args' => array(),
					'results' => true,
					'time' => 100,
				),
			),
		);

		$this->assertEqual($expected, Mocker::mergeResults($results, $staticResults));
	}

	public function testCreateFunction() {
		$obj = new \lithium\tests\mocks\test\MockStdClass;
		Mocker::overwriteFunction('lithium\tests\mocks\test\get_class', function() {
			return 'foo';
		});
		$this->assertIdentical('foo', $obj->getClass());
	}

	public function testCallFunctionUsesGlobalFallback() {
		$result = Mocker::callFunction('foo\bar\baz\get_called_class');
		$this->assertIdentical('lithium\test\Mocker', $result);
	}

	public function testMultipleCreateFunction() {
		$obj = new \lithium\tests\mocks\test\MockStdClass;
		Mocker::overwriteFunction('lithium\tests\mocks\test\get_class', function() {
			return 'foo';
		});
		Mocker::overwriteFunction('lithium\tests\mocks\test\get_class', function() {
			return 'bar';
		});
		$this->assertIdentical('bar', $obj->getClass());
	}

	public function testResetSpecificFunctions() {
		$obj = new \lithium\tests\mocks\test\MockStdClass;
		Mocker::overwriteFunction('lithium\tests\mocks\test\get_class', function() {
			return 'baz';
		});
		Mocker::overwriteFunction('lithium\tests\mocks\test\is_executable', function() {
			return 'qux';
		});
		Mocker::overwriteFunction('lithium\tests\mocks\test\get_class', false);

		$this->assertIdentical('lithium\tests\mocks\test\MockStdClass', $obj->getClass());
		$this->assertIdentical('qux', $obj->isExecutable());
	}

	public function testResetAllFunctions() {
		$obj = new \lithium\tests\mocks\test\MockStdClass;
		Mocker::overwriteFunction('lithium\tests\mocks\test\get_class', function() {
			return 'baz';
		});
		Mocker::overwriteFunction('lithium\tests\mocks\test\is_executable', function() {
			return 'qux';
		});
		Mocker::overwriteFunction(false);

		$this->assertIdentical('lithium\tests\mocks\test\MockStdClass', $obj->getClass());
		$this->assertInternalType('bool', $obj->isExecutable());
	}

	public function testMagicCallGetStoredResultsWhenCalled() {
		$obj = new \lithium\tests\mocks\test\mockStdClass\Mock;

		$obj->__call('foo', array());
		$results = Mocker::mergeResults($obj->results, $obj::$staticResults);

		$this->assertArrayHasKey('__call', $results);
		$this->assertArrayNotHasKey('__callStatic', $results);
	}

	public function testMagicCallStaticGetStoredResultsWhenCalled() {
		$obj = new \lithium\tests\mocks\test\mockStdClass\Mock;

		$obj->__callStatic('foo', array());
		$results = Mocker::mergeResults($obj->results, $obj::$staticResults);

		$this->assertArrayHasKey('__callStatic', $results);
		$this->assertArrayNotHasKey('__call', $results);
	}

	public function testMagicCallGetStoredResultsWhenCalledIndirectly() {
		$obj = new \lithium\tests\mocks\test\mockStdClass\Mock;

		$obj->methodBar();
		$results = Mocker::mergeResults($obj->results, $obj::$staticResults);

		$this->assertArrayHasKey('__call', $results);
		$this->assertCount(2, $results['__call']);
	}

	public function testDoesNotThrowExceptionWhenMockingIterator() {
		$this->assertNotException('Exception', function() {
			return new \lithium\util\collection\Mock;
		});
	}

	public function testMockDocument() {
		$document = new Document();
	}

	public function testMockModel() {
		$entity = MockPost::create();
	}

	public function testConstructParams() {
		$expected = 'lithium\tests\mocks\data\MockPost';
		$document = new Document(array('model' => $expected));
		$this->assertIdentical($expected, $document->model());
	}

}

?>