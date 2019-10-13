<?php
// index.php: The main file in the Guard project.
//
// Guard acts as a REST interface for database engines. It is built for MySQL and MariaDB, however
// is built in a way which will make porting to other engines easy, provided a table-like model can
// be emulated.
//
// (c) 2019 thatlittlegit. This code is licensed under the Apache License, 2.0. See the LICENSE file
// of this project.
$_SERVER["REQUEST_URI"] = str_replace("guard", "guard/index.php/databasegateway", $_SERVER["REQUEST_URI"]);

require_once "vendor/autoload.php";
use Luracast\Restler\Restler;
use Luracast\Restler\RestException;
use Luracast\Restler\Defaults;
use Luracast\Restler\iCache;

class FakeCache {
	public function set($name, $data) { return true; } 	
	public function get($name, $ignoreErrors = false) {}
	public function clear($name, $ignoreErrors = false) { return true; }
	public function isCached($name) { return false; }
}

Defaults::$cacheClass = 'FakeCache';
Defaults::$cacheDirectory = '/tmp';

function traversableToArray($traversable) {
	$temp = [];
	array_push($temp, ...$traversable);
	return $temp;
}

// NOTE trims to twenty characters
function alphanumeric($text) {
	return preg_replace("/[^a-zA-Z0-9_]+/", "", substr($text, 0, 20));
}

function excludeQuotes($text) {
	return str_replace("'", "\'", $text);
}

class DatabaseGateway {
	protected $conn;

	function __construct() {
		$DATABASE_URI="mysql:host=localhost;dbname=";

		if (!isset($_SERVER['PHP_AUTH_USER'])) {
			header('WWW-Authenticate: Basic realm="login for your database"');
			throw new RestException(401, "no authentication");
		}

		try {
			$this->conn = new PDO($DATABASE_URI . $_GET['_database'], substr($_SERVER['PHP_AUTH_USER'], 0, 200), substr($_SERVER['PHP_AUTH_PW'], 0, 200));
		} catch (Exception $error) {
			if ($error->getCode() === 1045 /* Access denied */) {
				header('WWW-Authenticate: Basic realm="login for your database"');
				throw new RestException(401, "invalid authentication");
			} else if ($error->getCode() === 1049 /* Unknown database */) {
				throw new RestException(500, "no such database (this error shouldn't be visible)");
			} else {
				throw $error;
			}
		}
		$this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}

	function tables() {
		$query = $this->conn->query("SHOW TABLES");
		return array(
			'tables' => array_map(function ($item) {
				return $item[0];
			}, traversableToArray($query)),
			'length' => $query->rowCount(),
		);
	}

	/** @url GET item */
	function get_item($table, $id) {
		if (!$this->tableExists($table)) { throw new RestException(404, "Table not found"); }
		$cleanedtable = alphanumeric($table);
		$statement = $this->conn->prepare("SELECT * FROM $cleanedtable WHERE id = ?");
		$statement->setFetchMode(PDO::FETCH_ASSOC);
		$statement->execute(array($id));

		if ($statement->rowCount() <= 0) { throw new RestException(404, "Row not found"); }
		return array(
			'table' => $cleanedtable,
			'object' => $statement->rowCount() > 0 ? traversableToArray($statement)[0] : null,
		);
	}
	
	/** @url POST item */
	function http_create_item($_table, $request_data = null) {
		$cleanedtable = alphanumeric($_table);
		unset($request_data["_table"]);

		$colNames = "(`" . implode(array_map('alphanumeric', array_keys($request_data)), "`, `") . "`)";
		$colValues = "('" . implode(array_map('excludeQuotes', $request_data), "','") . "')";
		$query = "INSERT INTO $cleanedtable $colNames VALUES $colValues";
		$executed = $this->conn->query($query);
		return array(
			'success' => true,
		);
	}

	/** @url PATCH item 
	  * @url PUT item */
	function modify_item($_table, $id, $request_data) {
		$cleanedtable = alphanumeric($_table);
		unset($request_data["_table"]);
		unset($request_data["id"]);
		
		$keys = array_keys($request_data);
		$values = array_values($request_data);
		function cb($key, $value) {
			$cleanedKey = alphanumeric($key);
			$cleanedValue = excludeQuotes($value);
			return "`$cleanedKey` = '$cleanedValue'";
		}
		$changes = implode(array_map('cb', $keys, $values), ', ');
		$query = $this->conn->prepare("UPDATE `$cleanedtable` SET $changes WHERE id = ?");
		$query->execute(array($id));
		return array(
			'success' => $query->rowCount() > 0 ? true : null,
		);
	}
	
	/** @url DELETE item */
	function delete_item($table, $id) {
		$cleanedtable = alphanumeric($table);

		$statement = $this->conn->prepare("DELETE FROM $cleanedtable WHERE id = ?");
		$statement->execute(array($id));
		return array(
			'success' => true,
		);
	}

	function items($table, $page) {
		$page = (int)$page;
		$cleanedtable = alphanumeric($table);
		
		$minvalue = ($page * 100) - 100;
		$maxvalue = ($page * 100);

		// No SQLi possible because $page is a number, and we control the rest
		$query = $this->conn->query("SELECT * FROM $cleanedtable WHERE id > $minvalue AND id <= $maxvalue");
		$query->setFetchMode(PDO::FETCH_ASSOC);
		return array(
			'meta' => array(
				'pageNumber' => $page,
				'minID' => $minvalue + 1,
				'maxID' => $maxvalue,
				'table' => $cleanedtable,
			),
			'items' => traversableToArray($query),
			'length' => $query->rowCount(),
		);
	}
	
	protected function tableExists($table) {
		$cleanedtable = alphanumeric($table);
		return ($this->conn->query("SHOW TABLES LIKE '$cleanedtable'")->rowCount() > 0);
	}
}

$r = new Restler(true);
$r->addAPIClass('DatabaseGateway'); // repeat for more
$r->handle(); //serve the response
?>
