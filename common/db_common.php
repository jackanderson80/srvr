<?php
	setlocale(LC_ALL, 'en_US.UTF8');
	
	function db_connect($hostname, $username, $password, $database, $port)
	{
		try
		{
			$mysqli = new mysqli($hostname, $username, $password, $database, $port);
			if ($mysqli->connect_errno) {
				$status = false;
				$error = "Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
				return null;
			}
		}
		catch(Exception $e)
		{
			$status = false;
			$error = 'exception: ' . $e;
			return null;
		}

		return $mysqli;
	}

	function db_disconnect($mysqli)
	{
		try
		{
			if ($mysqli)
				$mysqli->close();
		}
		catch(Exception $e)
		{
			$error = 'exception: ' . $e;
		}
	}

	function db_create($hostname, $username, $password, $new_database_name, $port)
	{
		$status = true;
		$result = "";
		$error = "";

		try
		{
			// connect to the MySQL server
			$mysqli = new mysqli($hostname, $username, $password, null, $port);

			// check connection
			if (mysqli_connect_errno()) {
				$status = false;
				$error = "Cannot connect to MySQL server";
			}
			else
			{
				$sqlQuery = "CREATE DATABASE `" . $new_database_name . "` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci";

				if ($mysqli->query($sqlQuery) === false)
				{
					$error = "Cannot create database";
					$status = false;
				}
				
				$mysqli->close();
				unset($mysqli);
			}
		}
		catch(Exception $e)
		{
			$status = false;
			$error = 'exception: ' . $e;
		}

		$out = array('data' => $result, 'status' => $status, 'error' => $error);
		return $out;
	}

	function db_delete($hostname, $username, $password, $database_name, $port)
	{
		try
		{
			// connect to the MySQL server
			$mysqli = new mysqli($hostname, $username, $password, null, $port);

			// check connection
			if (mysqli_connect_errno()) {
				return false;
			}
 
			$sqlQuery = "DROP DATABASE `" . $database_name . "`";

			if ($mysqli->query($sqlQuery) === TRUE)
			{
				$mysqli->close();
				unset($mysqli);
				
				return true;
			}
			
			$mysqli->close();
			unset($mysqli);
		}
		catch(Exception $e)
		{
			$status = false;
			$error = 'exception: ' . $e;
		}

		return false;
	}	
	
?>