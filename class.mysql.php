<?php
/*
 *  Copyright (C) 2022
 *     Alexander Momot (https://github.com/abilogic)
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is
 *  furnished to do so, subject to the following conditions:

 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.

 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 *  SOFTWARE.
*/

class MysqliObj {
	/**
	* Static MySQL link
	*/
	protected static $__mysqli;

	/**
	* List of queries
	*/
	protected $queriesList = array();

	/**
	* Add successful queries into the list with duration
	*/
	protected function addQuery($query, $duration) {
		$this->queriesList[] = array('query' => $query, 'duration' => $duration);
	}

	/**
	* Create MysqliObj via multiple parameters or via array options as a first parameter
	*
	* @param string|array $host
	* @param string $username
	* @param string $password
	* @param string $database
	* @param int    $port
	* @param string $socket
	* @param string $charset
	*/
	public function __construct($host, string $username = '', string $password = '', string $database = '', $port = 3306, $socket = null, $charset = null) {

		if (is_array($host)) {
			foreach ($host as $key => $val) {
				$$key = $val;
			}
		}

		if (empty($host)) {
			throw new Exception('Invalid connection parameters');
		}

		mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

		self::$__mysqli = new mysqli($host, $username, $password, $database, $port, $socket);

		if (is_string($charset)) {
			self::$__mysqli->set_charset($charset);
		}

	}

	/**
	* Close MySQL connection on destruction
	*/
	public function __destruct() {
		if (self::$__mysqli) {
			self::$__mysqli->close();
		}

	}

	/**
	* Query function
	*
	* @param string $query
	* @param array|null $params
	* @param int $numrows - 0 for all rows
	* @return data as an array for DQL queries and true for other successful queries
	*/
	public function query(string $query, $params = null, int $numrows = 0) {
		$starttime = microtime(true);
		$stmt = self::$__mysqli->prepare($query);

		if (is_array($params)) {
			$arr_typ = array();
			$arr_val = array();
			foreach ($params as $param) {
				if (is_string($param)) {
					$arr_typ[] = 's';
				} else if(is_int($param)) {
					$arr_typ[] = 'i';
				} else if(is_float($param)) {
					$arr_typ[] = 'd';
				} else {
					continue;
				}
				$arr_val[] = $param;
			}
			if (count($arr_typ)) {
				$stmt->bind_param(implode('', $arr_typ), ...$arr_val);
			}
		}

		$stmt_exec = $stmt->execute();
		$duration = microtime(true) - $starttime;

		if (!$stmt_exec) {
			return false;
		}

		$this->addQuery($query, $duration);

		$stmt_result = $stmt->get_result();

		if ($stmt_result && !empty($stmt_result)) {

			$rows = array();
			while ($row_data = $stmt_result->fetch_assoc()) {
				$rows[] = $row_data;
				if ($numrows && count($rows) >= $numrows) {
					break;
				}
			}
			return $rows;
		} else {
			return true;
		}
	}

	/**
	* Get a single row for DQL queries
	*
	* @param string $query
	* @param array|null $params
	* @param int $numrows - 0 for all rows
	* @return data as an associated array
	*/
	public function queryGetRow(string $query, $params = null) {
		$result = $this->query($query, $params, 1);
		if (is_array($result) && count($result)) {
			return $result[0];
		} else {
			return false;
		}
	}

	/**
	* Insert a single record into table
	*
	* @param string $tablename
	* @param array $data - associated array
	* @return true (successful) or false
	*/
	public function insertRow(string $tablename, $data = null) {

		if (!is_array($data)) {
			throw new Exception('Invalid data parameter');
		}

		$arr_fields = array_keys($data);
		$arr_values = array_values($data);

		$arr_fields = array_map(function($value) {
			return "`{$value}`";
		}, $arr_fields);

		$str_params = implode(',', array_fill(0, count($arr_fields), '?'));
		$str_fields = implode(',', $arr_fields);

		$query = "INSERT INTO {$tablename}({$str_fields})VALUES($str_params)";

		return $this->query($query, $arr_values);
	}

	/**
	* Get list of executed queries
	*
	* @return associated array with queries and duration
	*/
	public function getQueriesData() {
		return $this->queriesList;
	}
}

//------------------------------------------------------------------------------

?>