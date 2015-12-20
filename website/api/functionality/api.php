<?php

# Description: Shoplab backend API
# Author: Fredrik Berglund
# Date: 2015-12-19

require_once __DIR__ . '/../../api-config/config.php';

class ShoplabAPI {
	protected $mysqli;

	function __construct() {
		$this->mysqli = new mysqli(db_host, db_user, db_pass, db_db);

		if ($this->mysqli->connect_error) {
			throw new Exception('Could not connect to database.');
		}

		$this->mysqli->set_charset('utf8');

		mb_internal_encoding('utf-8');
	}

	function __destruct() {
		$this->mysqli->close();
	}

	# Returns token if valid credentials, false otherwise
	public function auth($user, $pass) {
		$email = $this->mysqli->real_escape_string($user);
		$password = $this->mysqli->real_escape_string($pass);

		$sql = '
			SELECT 
				user_id
			FROM
				user
			WHERE
				email = "' . $email . '" AND
				password = "' . $password . '" AND
				deleted_ts = 0
			LIMIT 1';

		if (true == ($res = $this->mysqli->query($sql))) {
			if (1 == $res->num_rows) {
				$token = password_hash($email . microtime(), PASSWORD_DEFAULT);
				$row = $res->fetch_assoc();
				$this->update_login_expire($token, $row['user_id']);
				return $token;
			}
		}

		return false;
	}

	# Update login expiration time of user_id with selected token
	protected function update_login_expire($token, $user_id = 0) {
		$now = time();

		$sql_token = $this->mysqli->real_escape_string($token);

		$sql_del_expired = '
			DELETE FROM
				user_login
			WHERE
				login_expires_ts < ' . $now;

		$this->mysqli->query($sql_del_expired);

		if (0 != $user_id) {
			$sql_del_old_token = '
				DELETE FROM
					user_login
				WHERE
					user_id = ' . $user_id . '
				LIMIT 1';

			$this->mysqli->query($sql_del_old_token);

			$sql_insert_token = '
				INSERT INTO
					user_login (fk_user_id, token, login_expires_ts)
				VALUES
					(' . $user_id . ', "' . $sql_token . '", ' . $now . ')';

			$this->mysqli->query($sql_insert_token);

			if (0 != $this->mysqli->affected_rows) {
				return true;
			}
		} else {
			$sql_update = '
				UPDATE
					user_login
				SET
					login_expires_ts = ' . $now . '
				WHERE
					token = "' . $token . '"
				LIMIT 1';

			$this->mysqli->query($sql_update);

			if (0 != $this->mysqli->affected_rows) {
				return true;
			}
		}

		return false;
	}
}