<?php

class DBConn {

	private $db_conn;
	public $insert_id;

	public function __construct($override_ini = null) {
		if (!is_null($override_ini)) {
			$db_settings = parse_ini_file($override_ini);
			$this->db_conn = new mysqli($db_settings['host'], $db_settings['username'], $db_settings['password'], $db_settings['database']);
		} else {
			$db_settings = parse_ini_file('/var/configs/db.ini');
			$this->db_conn = new mysqli($db_settings['host'], $db_settings['username'], $db_settings['password'], $db_settings['database']);
		}
	}

	public function getRow($sql_statement) {
		return $this->executeQuery($sql_statement, 'single');
	}

	public function getArray($sql_statement) {
		return $this->executeQuery($sql_statement, 'multi');
	}

	public function runQuery($sql_statement) {
		return $this->executeQuery($sql_statement, 'none');
	}

	public function getCharSet() {
		return $this->db_conn->character_set_name();
	}

	public function setCharSet($passed_string) {
		return $this->db_conn->set_charset($passed_string);
	}

	public function escapeString($passed_string) {
		return $this->db_conn->escape_string($passed_string);
	}

	public function lastInsertId() {
		return $this->db_conn->insert_id;
	}

	private function executeQuery($sql_statement, $return_type) {
		if ($this->db_conn->multi_query($sql_statement)) {
			if ($return_type == 'single') {
				if ($result = $this->db_conn->store_result()) {
					$row = $result->fetch_assoc();
					$result->free();
				}

				while ($this->db_conn->more_results() && $this->db_conn->next_result()) {
					$extraResult = $this->db_conn->use_result();
					if ($extraResult instanceof mysqli_result) {
						$extraResult->free();
					}
				}

				return $row;
			} elseif ($return_type == 'multi') {
				$result_arr = array();
				if ($result = $this->db_conn->store_result()) {
					while ($row = $result->fetch_assoc()) {
						$result_arr[] = $row;
					}
					$result->free();
				}

				while ($this->db_conn->more_results() && $this->db_conn->next_result()) {
					$extraResult = $this->db_conn->use_result();
					if ($extraResult instanceof mysqli_result) {
						$extraResult->free();
					}
				}

				return $result_arr;
			} elseif ($return_type == 'none') {
				return true;
			}
		} else {
			error_log("SQL ERROR:" . $this->db_conn->error, 0);
			error_log("SQL QUERY:" . $sql_statement, 0);
			return false;
		}
	}
}