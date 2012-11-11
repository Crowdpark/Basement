<?php
/**
 * Basement: The simple ODM for Couchbase on PHP
 *
 * @copyright     Copyright 2012, Michael Nitschinger
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

use Basement\Client;
use Basement\data\Document;

class ClientTest extends PHPUnit_Framework_TestCase {

	/**
	 * Use this to override the default config settings.
	 */
	protected $_testConfig = array(
		'host' => '192.168.56.101'
	);

	/**
	 * Delete the given keys to leave the bucket in a clean state.
	 */
	protected function _deleteKeys($keys = array()) {
		$client = new Client($this->_testConfig);

		foreach($keys as $key) {
			$client->connection()->delete($key);
		}
	}

	/**
	 * Tests the default configuration settings.
	 */
	public function testDefaultSettings() {
		$client = new Client(array('connect' => false));

		$expected = array(
			'host' => '127.0.0.1',
			'bucket' => 'default',
			'password' => '',
			'user' => null,
			'persist' => false
		);

		$config = $client->config();
		unset($config['connect']);

		$this->assertEquals($expected, $config);
	}

	/**
	 * Tests that when connect is false it does not connect.
	 */
	public function testConnectParam() {
		$client = new Client(array('connect' => false));
		$config = $client->config();

		$this->assertFalse($config['connect']);
		$this->assertFalse($client->connected());
		$this->assertFalse($client->connection());
	}

	/**
	 * Tests the proper connection to the Couchbase cluster.
	 */
	public function testSuccessfulConnect() {
		$client = new Client($this->_testConfig);

		$this->assertTrue($client->connected());
		$this->assertInstanceOf('Couchbase', $client->connection());
		$this->assertTrue($client->connect());
	}

	/**
	 * Tests the proper exception raising on connection failure.
	 *
	 * @expectedException RuntimeException
	 */
	public function testInvalidConnectHost() {
		$client = new Client(array('host' => '1.2.3.4'));
	}

	/**
	 * Tests the proper connection to the Couchbase cluster.
	 *
	 * There is something not working so this is skipped for now.
	 */
	public function testSuccessfulConnectWithTwoHosts() {
		$this->markTestSkipped('Skipped because of a bug in the SDK.');

		$config = array('host' => array('1.2.3.4', $this->_testConfig['host']));
		$client = new Client($config);

		$this->assertTrue($client->connected());
		$this->assertInstanceOf('Couchbase', $client->connection());
	}

	/**
	 * Verifies the proper usage of the different version variations.
	 */
	public function testVersion() {
		$versionRegex = '/(\d)+\.(\d)+.(\d)+(-(\w)+)*/';
		$client = new Client($this->_testConfig);

		$this->assertRegexp($versionRegex, $client->version());
		$this->assertRegexp($versionRegex, $client->version('client'));

		$result = $client->version('cluster');
		$this->assertGreaterThanOrEqual(1, count($result));
		foreach($result as $addr => $version) {
			$this->assertTrue((is_string($addr) && !empty($addr)));
			$this->assertTrue((is_string($addr) && !empty($version)));
		}
	}

	/**
	 * Tests the generation of key names with optional prefixes.
	 */
	public function testGenerateKey() {
		$result1 = Client::generateKey();
		$this->assertTrue(is_string($result1));
		$this->assertRegexp('/\w+/', $result1);

		$result2 = Client::generateKey();
		$this->assertNotEquals($result1, $result2);
	}

	/**
	 * Tests the save operation with default settings and an array
	 * as the document to store.
	 */
	public function testSaveWithDefaultSettingsAndArray() {
		$client = new Client($this->_testConfig);

		$key = 'testdocument-1';
		$doc = array('foobar');
		$result = $client->save(compact('key', 'doc'));
		$this->assertTrue(is_string($result));
		$this->assertNotEmpty($result);

		$check = $client->connection()->get($key);
		$this->assertEquals($doc, json_decode($check));

		$this->_deleteKeys(array($key));
	}

	/**
	 * Test with an array document that is not well formatted.
	 *
	 * @expectedException InvalidArgumentException
	 */
	public function testSaveWithInvalidArray() {
		$client = new Client($this->_testConfig);
		$document = array('just' => 'some', 'data');
		$result = $client->save($document);	
	}

	/**
	 * Test with a string as document.
	 *
	 * @expectedException InvalidArgumentException
	 */
	public function testSaveWithInvalidString() {
		$client = new Client($this->_testConfig);
		$result = $client->save("storeme");	
	}

	/**
	 * Test the correct saving of a Basement Document.
	 */
	public function testSaveWithDefaultSettingsAndDocument() {
		$client = new Client($this->_testConfig);

		$key = 'testdocument-1';
		$doc = array('foobar');
		$document = new Document(compact('key', 'doc'));
		$result = $client->save($document);
		$this->assertTrue(is_string($result));
		$this->assertNotEmpty($result);

		$check = $client->connection()->get($key);
		$this->assertEquals($doc, json_decode($check));

		$this->_deleteKeys(array($key));
	}

	/**
	 * Tests the save command with the add operation.
	 */
	public function testSaveWithNoOverride() {
		$this->markTestIncomplete('This test has not been implemented yet.');
	}

	/**
	 * Tests the save command with the replace operation.
	 */
	public function testSaveWithReplace() {
		$this->markTestIncomplete('This test has not been implemented yet.');
	}

}


?>