<?php
/**
 * Basement: The simple ODM for Couchbase on PHP
 *
 * @copyright     Copyright 2012, Michael Nitschinger
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace Basement;

use \Couchbase;
use \RuntimeException;
use \InvalidArgumentException;

/**
 * The `Client`class is your main entry point when working with your Couchbase cluster.
 *
 * It provides the convenience methods around the PHP SDK and also handles connection
 * management. Together with all the supporting classes like the Basement Document and
 * Model it makes it easier to develop applications on Couchbase and PHP than ever before.
 */
class Client {

	/**
	 * Holds the current config.
	 */
	protected $_config;

	/**
	 * Holds the connection resource if connected.
	 */
	protected $_connection = null;

	/**
	 * Create and connect to the CouchbaseClient.
	 *
	 * If no user is provided (or is null), then it is assumed to be the same
	 * name as the bucket. This is the common behavior and seldom needs to be
	 * changed.
	 */
	public function __construct($options = array()) {
		$defaults = array(
			'host' => '127.0.0.1',
			'bucket' => 'default',
			'password' => '',
			'user' => null,
			'persist' => false,
			'connect' => true
		);
		$this->_config = $options + $defaults;
		$this->_config['user'] ?: $this->_config['bucket'];

		if($this->_config['connect'] == true) {
			$this->connect();
		}
	}

	/**
	 * Returns the current configuration.
	 */
	public function config() {
		return $this->_config;
	}

	/**
	 * Connects to the CouchbaseClient.
	 */
	public function connect() {
		if($this->connected()) {
			return true;
		}
		extract($this->_config);

		set_error_handler(function($no, $str, $file, $line, array $context) {
			throw new RuntimeException($str);
		});

		$this->_connection = new Couchbase($host, $user, $password, $bucket);

		restore_error_handler();

		return $this->connected();
	}

	/**
	 * Returns the sate of the connection.
	 */
	public function connected() {
		return $this->_connection !== null;
	}

	/**
	 * Returns the connection resource if connected.
	 */
	public function connection() {
		return $this->connected() ? $this->_connection : false;
	}

	/**
	 * Gathers information on both the client and cluster versions.
	 *
	 * Depending on the given type (either cluster or client, which is the default), the
	 * method returns the appropriate versions. Note that for the cluster type, an 
	 * array is returned with version information for each node. This means that it makes
	 * it easy to count the number of nodes in the cluster as well.
	 *
	 * The version returned for the cluster is the used memcached version, not the 
	 * version of couchbase server itself.
	 */
	public function version($type = 'client') {
		if($type == 'client') {
			return $this->_connection->getClientVersion();
		} elseif($type == 'cluster') {
			return $this->_connection->getVersion();
		}
	}

	/**
	 * Store a document in Couchbase.
	 *
	 * The document can be any object or array that can be serialized or somehow stored in
	 * Couchbase. By default, the method will try to encode it as JSON and store it
	 * in the cluster. If you pass it an instance of \Basement\Document then you have
	 * much more control on what to do with the object. See the documentation
	 * for more information on that object. If you use a plain array then it will have to
	 * have the following layout:
	 *
	 *		array('key' => $key, 'doc' => $doc)
	 *
	 * This is important because you need a unique key to store documents in couchbase. The
	 * value of doc can be anything that PHP can convert to JSON (or serialize it). The key
	 * has to be a string. You can use the `generateKey()` method to generate a 
	 * (theoretically) uniqe key.
	 *
	 * You can also pass in various options. By default, when override is true and replace
	 * is false, the `set` operation is used which means that the document will just
	 * be overriden. If you set `override` false, the `add` operation will be used instead
	 * and therefore fail if the document did already exist. If `replace`is set to true,
	 * the `replace` operation will be used and it will fail if the document didn't exist
	 * before. If you set `serialize` to true, the document will be stored as a serialized
	 * object instead of a JSON one. Keep in mind that this removes the ability to query
	 * the document through a view. Finally, you can pass in an expiration time or the
	 * CAS value.
	 *
	 * If the operation did succeed, the CAS value is returned, otherwise false.
 	 */
	public function save($document, $options = array()) {
		$defaults = array(
			'override' => true,
			'replace' => false,
			'serialize' => false,
			'expiration' => 0,
			'cas' => '0'
		);
		$params = $options + $defaults;

		$extractKey = function() use ($document) {
			if($document instanceof \Basement\data\Document) {
				return $document->key();
			} elseif(is_array($document) && !empty($document['key'])) {
				return $document['key'];
			} else {
				throw new InvalidArgumentException("Invald document type given.");
			}
		};

		$stringify = function($serialize) use ($document) {
			if(is_array($document) && empty($document['key'])) {
				throw new InvalidArgumentException("Invalid or no key given.");
			} elseif(is_array($document) && !isset($document['doc'])) {
				throw new InvalidArgumentException("No 'doc' given.");
			}

			if($serialize) {
				if($document instanceof \Basement\data\Document) {
					return $document->serialize();
				} else {
					return serialize($document['doc']);
				}
			} else {
				if($document instanceof \Basement\data\Document) {
					return $document->toJson();
				} else { 
					return json_encode($document['doc']);
				}	
			}
		};

		$key = $extractKey();
		$stringified = $stringify($params['serialize'] == true);

		if($params['override'] == true && $params['replace'] == false) {
			$operation = 'set';
		} elseif($params['override'] == false) {
			$operation = 'add';
		} elseif($params['replace'] == true) {
			$operation = 'replace';
		} else {
			throw new InvalidArgumentException("Unsupported argument combination.");
		}

		$result = $this->_connection->{$operation}(
			$key, $stringified, $params['expiration'], $params['cas']
		);

		return $result ?: false;
	}

	/**
	 * Generate a unique key with an optional prefix.
	 */
	public static function generateKey($prefix = '') {
		return uniqid($prefix, false);
	}

}

?>