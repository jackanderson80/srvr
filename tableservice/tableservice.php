<?php
    require_once __DIR__ . "/../common/db_common.php";
    //require_once __DIR__ . "/../config/cloudconnect.php";

    class TableManager
    {
        var $table_uuid = null;
        var $table_name = "";
        var $table_schema = [];
        var $sqlConnection = null;

        var $typeMap = array(
            'int' => 'int',
            'number' => 'double',
            'string' => 'varchar(255)',
            'double' => 'double',
            'datetime' => 'datetime',
            'date' => 'datetime',
            'time' => 'datetime',
            'boolean' => 'bit(1)',
            'option'=> 'varchar(255)',
            'multioption'=> 'text',
            'link'=> 'varchar(4096)',
            'filelink'=>'varchar(4096)',
            'imagelink'=> 'varchar(4096)',
            'text'=> 'mediumtext' // up to 16MB
        );
    

        function __construct($sqlConnection)
        {
            $this->sqlConnection = $sqlConnection;
        }

        private function _saveTableSchema()
        {
            $mysqli = $this->sqlConnection;

            $status = true;
            $error = "";
            $result = "";
        
            try
            { 
                // create the metadata table if it doesn't exist already
                $sqlQuery = "CREATE TABLE IF NOT EXISTS `tables_metadata` (" .
                    "`uuid` VARCHAR(36) NOT NULL," .
                    "`name` VARCHAR(255) NULL," .
                    "`metadata` VARCHAR(16384) NULL," .
                    "PRIMARY KEY (`uuid`));";                 

                if ($mysqli->query($sqlQuery) !== TRUE)
                {
                    $error = "Cannot create table metadata";
                    $status = false;
                    goto end;
                }

                // if exists, drop the tables upsert procedure
                $sqlQuery = "DROP PROCEDURE IF EXISTS `prc_UpsertTableMetadata`";
                if ($mysqli->query($sqlQuery) !== TRUE)
                {
                    $error = "Cannot create table metadata, error 1";
                    $status = false;
                    goto end;
                }

                // create the tables upsert procedure
                $sqlQuery = "CREATE PROCEDURE `prc_UpsertTableMetadata` (\ntable_uuid varchar(36),\ntable_name varchar(255),\nmetadata varchar(16384)";

                $sqlQuery = $sqlQuery . "\n)\nBEGIN\n";
    
                $sqlQuery = $sqlQuery . "\tDECLARE insert_uuid varchar(36);\n";

                $sqlQuery = $sqlQuery . "\tIF table_uuid is not null THEN\n";
    
                $sqlQuery = $sqlQuery . "\t\tSET insert_uuid = (SELECT table_uuid);\n";
    
                $sqlQuery = $sqlQuery . "\t\tUPDATE tables_metadata SET tables_metadata.name = table_name, tables_metadata.metadata = metadata";   
                $sqlQuery = $sqlQuery . "\n\t\tWHERE uuid=table_uuid;" . "\n";
                    
                $sqlQuery = $sqlQuery . "\tELSE\n";

                $sqlQuery = $sqlQuery . "\t\tSET insert_uuid = (SELECT uuid());\n";
    
                $sqlQuery = $sqlQuery . "\t\tINSERT INTO tables_metadata VALUES(insert_uuid, table_name, metadata);";

                $sqlQuery = $sqlQuery . "\n\tEND IF;\n";
    
                $sqlQuery = $sqlQuery . "\n\tSELECT insert_uuid as 'uuid';\n";

                $sqlQuery = $sqlQuery . "\nEND";
    
                if ($mysqli->query($sqlQuery) !== TRUE)
                {
                    $error = "Cannot setup table metadata updates, error 2";
                    $status = false;
                    goto end;
                }
            }
            catch(Exception $e)
            {
                $status = false;
                $error = 'exception: ' . $e;
            }

            $schemaString = json_encode($this->table_schema);

            $tableName = $this->table_name;
            if (!isset($tableName))
                $tableName = $this->_getInternalTableName();
            
            $out_upsert = $this->_upsertTableSchema($this->table_uuid, $tableName, $schemaString);
            if ($out_upsert["status"] != true)
            {
                $status = false;
                $error = "Failed to update table metadata";
                $result = "";
            }
            else
            {
                $this->table_uuid = $out_upsert["data"]["uuid"];
            }

            end:

            $out = array('data' => $result, 'status' => $status, 'error' => $error);

            return $out;    
        }

        private function _upsertTableSchema($uuid, $tableName, $schemaString)
        {
            $mysqli = $this->sqlConnection;

            $status = true;
            $error = "";
            $result = "";
        
            $procName = "prc_UpsertTableMetadata";
            $statement = "CALL " . $procName . "(?, ?, ?)";
            
            try
            {                   
                if (!($stmp = mysqli_prepare($this->sqlConnection, $statement)))
                {
                    $status = false;
                    
                    $error = "Internal error. Prepare failed: (" . $this->sqlConnection->errno . ") " . $this->sqlConnection->error;
                    goto end;
                }

                $params = [];
 
                array_push($params, "sss");
                array_push($params, $uuid);
                array_push($params, $tableName);
                array_push($params, $schemaString);

                call_user_func_array(array($stmp, 'bind_param'), $this->_refValues($params));

                if (!$stmp->execute())
                {
                    $status = false;
                    $error = $stmp->error;
                    goto end;
                }

                $stmp->bind_result($inserted_uuid);
                
                while ($stmp->fetch())
                {
                    $result = array(
                        "uuid" => $inserted_uuid
                    );
                }
            }
            catch(Exception $e)
            {
                $error = "Exception: " . $e;
                $status = false;
                $result = "";
            }

            end:

            $out = array('data' => $result, 'status' => $status, 'error' => $error);

            return $out;
        }

        function loadTable($uuid)
        {
            $status = true;
            $error = "";
            $result = "";

            $inner_result = $this->_loadTableSchema($uuid);
            if ($inner_result["status"] == false)
            {
                $status = false;
                $error = "Error loading table id " . $uuid;
                goto end;            
            }

            $this->table_uuid = $inner_result["data"]["uuid"];
            $this->table_name = $inner_result["data"]["name"];
            try
            {
                $this->table_schema = json_decode($inner_result["data"]["schema"], true);
            }
            catch(Exception $e)
            {
                $error = "Exception: " . $e;
                $status = false;
                $result = "";
            }
            end:

            $out = array('data' => $result, 'status' => $status, 'error' => $error);

            return $out;                
        }

        private function _loadTableSchema($table_uuid)
        {
            $mysqli = $this->sqlConnection;

            $status = true;
            $error = "";
            $result = "";
        
            $statement = "SELECT * FROM tables_metadata WHERE uuid='" . $table_uuid . "'";
            
            try
            {                   
                if (!($stmp = mysqli_prepare($this->sqlConnection, $statement)))
                {
                    $status = false;
                    
                    $error = "Internal error. Prepare failed: (" . $this->sqlConnection->errno . ") " . $this->sqlConnection->error;
                    goto end;
                }
 
                if (!$stmp->execute())
                {
                    $status = false;
                    $error = $stmp->error;
                    goto end;
                }

                $stmp->bind_result($uuid, $name, $schema);
                
                while ($stmp->fetch())
                {
                    $result = array(
                        "uuid" => $uuid,
                        "name" => $name,
                        "schema" => $schema
                    );
                }
            }
            catch(Exception $e)
            {
                $error = "Exception: " . $e;
                $status = false;
                $result = "";
            }

            end:

            $out = array('data' => $result, 'status' => $status, 'error' => $error);

            return $out;
        }

        private function _deleteTableSchema()
        {
            $mysqli = $this->sqlConnection;

            $status = true;
            $error = "";
            $result = "";

            if ($this->table_uuid === null)
                goto end;

            try
            {                   
                $sqlQuery = "DELETE FROM tables_metadata WHERE uuid = '" . $this->table_uuid . "'";

                if ($mysqli->query($sqlQuery) !== TRUE)
                {
                    $status = false;
                }
            }
            catch(Exception $e)
            {
                $error = "Exception: " . $e;
                $status = false;
                $result = "";
            }
            
            end:

            $out = array('data' => $result, 'status' => $status, 'error' => $error);

            return $out;
        }

        /*
        function getColumnByName($externalColumnName)
        {
            for ($i = 0; $i < count($this->table_schema); $i++)
            {
                if ($this->table_schema[$i]["name"] == $externalColumnName)
                    return $this->table_schema[$i];
            }

            return null;
        }*/

        function getTableId()
        {
            $out = array('data' => Array('table_uuid' => $this->table_uuid), 'status' => true, 'error' => '');

            return $out ;
        }

        function getTableSchema()
        {
            if ($this->table_schema === null)
                $out = array('data' => '', 'data2' => Array('table_schema' => $this->table_schema), 'status' => false, 'error' => 'Invalid table schema');
            else
                $out = array('data' => Array('table_schema' => $this->table_schema), 'status' => true, 'error' => '');               

            return $out ;
        }

        private function _getInternalTableName()
        {
            return "ws_table_" . $this->table_uuid;
        }

        private function _generateUpsertProc()
        {
            $columns = $this->table_schema;

            $tableName = $this->_getInternalTableName();
            $upsertProcName = "prc_upsert_" . $tableName;

            $sql = "CREATE PROCEDURE `" . $upsertProcName . "` (\n\trecord_uuid int";

            for ($i = 0; $i < count($columns); $i++)
            {
                $sql = $sql .",\n";

                $column = $columns[$i];
                $sqlType = $this->typeMap[$column["type"]];
                $columnDBIndex = $columns[$i]["column_id"];
        
                // medium text - 16MB
                // long text - 4GB
        
                $sql = $sql . "\tvalue_" . $columnDBIndex . " " . $sqlType;
            }

            $sql = $sql . "\n)\nBEGIN\n";

            $sql = $sql . "\tSTART TRANSACTION;\n";

            $sql = $sql . "\tIF record_uuid is not null THEN\n";

            $sql = $sql . "\t\tUPDATE `" . $tableName . "` SET \n";

            $sql = $sql . "\t\t\tuuid=record_uuid";
            for ($i = 0; $i < count($columns); $i++)
            {
                $sql = $sql .",\n";

                $columnDBIndex = $columns[$i]["column_id"];
                $columnDBName = "column_" . $columnDBIndex;

                $sql = $sql . "\t\t\t" . $columnDBName . " = value_" . $columnDBIndex;
            }

            $sql = $sql . "\n\t\tWHERE uuid=record_uuid;" . "\n";

            $sql = $sql . "\n\t\tSELECT record_uuid as `insert_uuid`;\n";

            $sql = $sql . "\tELSE\n";

            $sql = $sql . "\t\tINSERT INTO `" . $tableName . "` SET\n\t\t\tuuid=NULL";

            for ($i = 0; $i < count($columns); $i++)
            {
                $sql = $sql .",\n";

                $columnDBIndex = $columns[$i]["column_id"];
                $columnDBName = "column_" . $columnDBIndex;

                $sql = $sql . "\t\t\t" . $columnDBName . " = value_" . $columnDBIndex;
            }

            $sql = $sql . ";";

            $sql = $sql . "\n\t\tSELECT LAST_INSERT_ID() as `insert_uuid`;\n";

            $sql = $sql . "\tEND IF;\n";
            
            $sql = $sql . "\tCOMMIT;\n";

            $sql = $sql . "\nEND";

            return $sql;
        }

        private function _generateDeleteProc()
        {
            $tableName = $this->_getInternalTableName();
            $upsertProcName = "prc_delete_" . $tableName;

            $sql = "CREATE PROCEDURE `" . $upsertProcName . "` (\nrecord_uuid int";

            $sql = $sql . "\n)\nBEGIN\n";

            $sql = $sql . "\tDELETE FROM `" . $tableName . "`\n\tWHERE\n\t\tuuid=record_uuid;";
            
            $sql = $sql . "\nEND";

            return $sql;
        }

        private function _generateCreateSchema()
        {
            $columns = $this->table_schema;

            $tableName = $this->_getInternalTableName();
            $sql = "CREATE TABLE `" . $tableName . "` (\n";
            
            // add the uuid
            $sql = $sql . "`uuid` INT NOT NULL AUTO_INCREMENT";
        
            for ($i = 0; $i < count($columns); $i++)
            {
                $column = $columns[$i];
                $sqlType = $this->typeMap[$column["type"]];
                if ($sqlType == null) // error
                    return "";
        
                // medium text - 16MB
                // long text - 4GB
                $sql = $sql .",\n";
        
                $sql = $sql . "`column_" . $i . "` " . $sqlType . ' NULL';
            }
        
            // make uuid the primary key and close the statement
            $sql = $sql . ",\nPRIMARY KEY (`uuid`)\n)";
        
            return $sql;
        }

        function deleteTable()
        {
            $mysqli = $this->sqlConnection;

            $status = true;
            $error = "";
            $result = "";
        
            try
            { 
                $mysqli->begin_transaction();

                $inner_result = $this->_deleteTableSchema();
                if ($inner_result["status"] == false)
                {
                    $error = $inner_result["error"];
                    $status = false;
                    $mysqli->rollback();

                    goto end;
                }

                $inner_result = $this->_dropTableProcedures();
                if ($inner_result["status"] == false)
                {
                    $error = $inner_result["error"];
                    $status = false;
                    $mysqli->rollback();
                    
                    goto end;
                }

                $tableName = $this->_getInternalTableName();
                $sqlQuery = "DROP TABLE IF EXISTS `" . $tableName . "`";

                if ($mysqli->query($sqlQuery) !== TRUE)
                {
                    $status = false;
                    $mysqli->rollback();
                    
                    goto end;
                }

                $mysqli->commit();
            }
            catch(Exception $e)
            {
                $status = false;
                $error = 'exception: ' . $e;
            }

            $this->table_uuid = null;

            end:

            $out = array('data' => $result, 'status' => $status, 'error' => $error);

            return $out;    
        }

        private function _dropTableProcedures()
        {
            $mysqli = $this->sqlConnection;
            $status = true;
            $error = "";
            $result = "";
        
            try
            { 
                $upsertProcName = "prc_upsert_" . $this->_getInternalTableName();
                $sqlQuery = "DROP PROCEDURE IF EXISTS `" . $upsertProcName . "`";
                if ($mysqli->query($sqlQuery) !== TRUE)
                {
                    $status = false;
                }

                $deleteProcName = "prc_delete_" . $this->_getInternalTableName();
                $sqlQuery = "DROP PROCEDURE IF EXISTS `" . $deleteProcName . "`";
                if ($mysqli->query($sqlQuery) !== TRUE)
                {
                    $status = false;
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

        function createTable($name, $schema)
        {
            $mysqli = $this->sqlConnection;

            $status = true;
            $error = "";
            $result = "";

            $saveTableName = $this->table_name;
            $savedSchema = array_merge([], $this->table_schema);

            $this->table_schema = $schema;
            $this->table_name = $name;

            for ($i = 0; $i < count($this->table_schema); $i++)
            {
                $this->table_schema[$i]["column_id"] = $i;
            }

            $inner_result = $this->deleteTable();
            if ($inner_result["status"] == false)
            {
                $status = false;
                $error = $inner_result["error"];
                goto end;
            }
    
            try
            { 
                $mysqli->begin_transaction();

                $inner_result = $this->_saveTableSchema();
                if ($inner_result["status"] == false)
                {
                    $mysqli->rollback();

                    $status = false;
                    $error = $inner_result["error"];
                    goto end;
                }

                $inner_result = $this->_internalCreateTable($schema);
                if ($inner_result["status"] == false)
                {
                    $mysqli->rollback();

                    $status = false;
                    $error = $inner_result["error"];
                    goto end;
                }

                $mysqli->commit();
            }
            catch(Exception $e)
            {
                $status = false;
                $error = 'exception: ' . $e;
            }

            end:

            if (!$status)
            {
                $this->table_uuid = null;
                $this->table_schema = array_merge([], $savedSchema);
                $this->table_name = $saveTableName;
            }
            else
            {
                $result = array("uuid" => $this->table_uuid);
            }


            $out = array('data' => $result, 'status' => $status, 'error' => $error);

            return $out;    
        }

        private function _internalCreateTable()
        {
            $mysqli = $this->sqlConnection;

            $status = true;
            $error = "";
            $result = "";
        
            try
            { 
                $sqlQuery = $this->_generateCreateSchema();

                if ($mysqli->query($sqlQuery) !== TRUE)
                {
                    $status = false;
                }


                $sqlQuery = $this->_generateUpsertProc();
                if ($sqlQuery == "")
                {
                    $out = array('data' => "", 'status' => false, 'error' => "Failed setting up table inserts & updates.");
                    return $out;
                }

                if ($mysqli->query($sqlQuery) !== TRUE)
                {
                    $status = false;
                }

                $sqlQuery = $this->_generateDeleteProc();
                if ($sqlQuery == "")
                {
                    $out = array('data' => "", 'status' => false, 'error' => "Failed setting up table deletes.");
                    return $out;
                }

                if ($mysqli->query($sqlQuery) !== TRUE)
                {
                    $status = false;
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

        private function _refValues($arr){
            if (strnatcmp(phpversion(),'5.3') >= 0) //Reference is required for PHP 5.3+
            {
                $refs = array();
                foreach($arr as $key => $value)
                    $refs[$key] = &$arr[$key];
                return $refs;
            }

            return $arr;
        }

        function getRecord($uuid)
        {
            $mysqli = $this->sqlConnection;

            $status = true;
            $error = "";
            $result = "";

            $internalTableName = $this->_getInternalTableName();
        
            $sqlQuery = "SELECT * FROM `" . $internalTableName . "` WHERE uuid='" . $uuid . "'";
            
            try
            {             
                if (!($stmp = mysqli_prepare($this->sqlConnection, $sqlQuery)))
                {
                    $status = false;
                    
                    $error = "Internal error. Prepare failed: (" . $this->sqlConnection->errno . ") " . $this->sqlConnection->error;
                    goto end;
                }
 
                if (!$stmp->execute())
                {
                    $status = false;
                    $error = $stmp->error;
                    goto end;
                }

                $fields = [];

                $columns = $this->table_schema;

                $uuid_var = null;
                $fields["uuid"] = &$uuid_var;
                for ($i = 0; $i < count($columns); $i++)
                {
                    $var = "column_" . $columns[$i]["column_id"];
                    $$var = null; 
                    $fields[$var] = &$$var;
                }

                
                call_user_func_array(array($stmp, 'bind_result'), $this->_refValues($fields));
                
                while ($stmp->fetch())
                {
                    $results = array();
                    foreach($fields as $k => $v)
                        $results[$k] = $v;

                    $result = [];
                    $result["uuid"] = $uuid;
                    for ($i = 0; $i < count($columns); $i++)
                    {
                        $id = "column_" . $columns[$i]["column_id"];
                        $name = $columns[$i]["name"];
                        
                        // escape the name not to conflict with the record uuid
                        if ($name == "uuid")
                            throw "uuid column name duplicate";
                            //$name = "\"uuid\"";

                        $result[$name] = $results[$id];
                    }
        
                }
            }
            catch(Exception $e)
            {
                $error = "Exception: " . $e;
                $status = false;
                $result = "";
            }

            end:

            $out = array('data' => $result, 'status' => $status, 'error' => $error);

            return $out;
        }

        function getRecords()
        {
            $mysqli = $this->sqlConnection;

            $status = true;
            $error = "";
            $result = [];

            $internalTableName = $this->_getInternalTableName();
        
            $sqlQuery = "SELECT * FROM `" . $internalTableName . "`;";
            
            try
            {             
                if (!($stmp = mysqli_prepare($this->sqlConnection, $sqlQuery)))
                {
                    $status = false;
                    
                    $error = "Internal error. Prepare failed: (" . $this->sqlConnection->errno . ") " . $this->sqlConnection->error;
                    goto end;
                }
 
                if (!$stmp->execute())
                {
                    $status = false;
                    $error = $stmp->error;
                    goto end;
                }

                $fields = [];

                $columns = $this->table_schema;

                $uuid_var = null;
                $fields["uuid"] = &$uuid_var;
                for ($i = 0; $i < count($columns); $i++)
                {
                    $var = "column_" . $columns[$i]["column_id"];
                    $$var = null; 
                    $fields[$var] = &$$var;
                }

                
                call_user_func_array(array($stmp, 'bind_result'), $this->_refValues($fields));
                
                $r = 0;
                while ($stmp->fetch())
                {
                    $results = array();
                    foreach($fields as $k => $v)
                        $results[$k] = $v;

                    $result[$r]["uuid"] = $results['uuid'];
                    for ($i = 0; $i < count($columns); $i++)
                    {
                        $id = "column_" . $columns[$i]["column_id"];
                        $name = $columns[$i]["name"];

                        // escape the name not to conflict with the record uuid
                        if ($name == "uuid")
                            throw "uuid column name duplicate";
                            //$name = "\"uuid\"";

                        $result[$r][$name] = $results[$id];
                    }
    
                    $r++;
                }
            }
            catch(Exception $e)
            {
                $error = "Exception: " . $e;
                $status = false;
                $result = "";
            }

            end:

            $out = array('data' => $result, 'status' => $status, 'error' => $error);

            return $out;
        }

        private function _upsertRecord($uuid, $columnValues)
        {            
            $status = true;
            $error = "";
            $result = "";
        
            $bindString = "s";

            $upsertProcName = "prc_upsert_" . $this->_getInternalTableName();
            $statement = "CALL `" . $upsertProcName . "` (?";

            $columns = $this->table_schema;

            for ($i = 0; $i < count($columns); $i++)
            {
                $statement = $statement . ", ?";

                $columnDBIndex = $columns[$i]["column_id"];

                $db_type = $this->typeMap[$columns[$i]["type"]];

                if (strpos($db_type, 'varchar') !== false)
                {
                    $bindString = $bindString . "s";
                }
                else if (strpos($db_type, 'double') !== false)
                {
                    $bindString = $bindString . "d";
                }
                else if (strpos($db_type, 'int') !== false || strpos($db_type, 'bit') !== false)
                {
                    $bindString = $bindString . "i";
                }
                else if (strpos($db_type, 'datetime') !== false)
                {
                    $bindString = $bindString . "s";
                }
                else if (strpos($db_type, 'text') !== false)
                {
                    $bindString = $bindString . "s";
                }
            }

            $statement = $statement . ")";
            
            try
            {                   
                if (!($stmp = mysqli_prepare($this->sqlConnection, $statement)))
                {
                    $status = false;
                    
                    $error = "Internal error. Prepare failed: (" . $this->sqlConnection->errno . ") " . $this->sqlConnection->error;
                    goto end;
                }

                $params = [];
 
                array_push($params, $bindString);
                array_push($params, $uuid);

                for ($i = 0; $i < count($columns); $i++)
                {
                    for ($j = 0; $j < count($columnValues); $j++)
                    {
                        if ($columns[$i]["name"] == $columnValues[$j]["name"])
                            break;
                    }

                    if ($j == count($columnValues))
                    {
                        $error = "Value for column " . $columns[$i]["name"] . " is not provided";
                        $status = false;
                        goto end;
                    }

                    array_push($params, $columnValues[$j]["value"]);
                }

                call_user_func_array(array($stmp, 'bind_param'), $this->_refValues($params));

                if (!$stmp->execute())
                {
                    $status = false;
                    $error = $stmp->error;
                    goto end;
                }

                $stmp->bind_result($inserted_uuid);

                while ($stmp->fetch())
                {
                    $result = array(
                        "record_uuid" => $inserted_uuid
                    );
                }
            }
            catch(Exception $e)
            {
                $error = "Exception: " . $e;
                $status = false;
                $result = "";
            }
    

            end:

            if (isset($stmp))
                $stmp->close();

            unset($stmp);
            
            $out = array('data' => $result, 'status' => $status, 'error' => $error);

            return $out;                
        }

        function addRecord($columnValues)
        {
            return $this->_upsertRecord(null, $columnValues);
        }

        function updateRecord($uuid, $columnValues)
        {
            return $this->_upsertRecord($uuid, $columnValues);
        }

        function deleteRecord($uuid)
        {
            $status = true;
            $error = "";
            $result = "";
        
            $bindString = "s";

            $deleteProcName = "prc_delete_" . $this->_getInternalTableName();
            $statement = "CALL `" . $deleteProcName . "` (?)";
            
            try
            {                   
                if (!($stmp = mysqli_prepare($this->sqlConnection, $statement)))
                {
                    $status = false;
                    
                    $error = "Internal error. Prepare failed: (" . $this->sqlConnection->errno . ") " . $this->sqlConnection->error;
                    goto end;
                }

                $params = [];
 
                array_push($params, $bindString);
                array_push($params, $uuid);

                call_user_func_array(array($stmp, 'bind_param'), $this->_refValues($params));

                if (!$stmp->execute())
                {
                    $status = false;
                    $error = $stmp->error;
                    goto end;
                }

                while ($stmp->fetch())
                {
                }
            }
            catch(Exception $e)
            {
                $error = "Exception: " . $e;
                $status = false;
                $result = "";
            }
    
            end:

            if ($stmp)
                $stmp->close();

            unset($stmp);
            
            $out = array('data' => $result, 'status' => $status, 'error' => $error);

            return $out;                

        }

        private function _updateUpsertProc()
        {
            $mysqli = $this->sqlConnection;

            $status = true;
            $error = "";
            $result = "";
        
            try
            { 
                $tableName = $this->_getInternalTableName();
                $upsertProcName = "prc_upsert_" . $tableName;
                $sqlQuery = "DROP PROCEDURE  IF EXISTS `" . $upsertProcName . "`;";
                if ($mysqli->query($sqlQuery) !== TRUE)
                {
                    $status = false;

                    goto end;
                }
    
                $sqlQuery = $this->_generateUpsertProc();
                if ($sqlQuery == "")
                {
                    $status = false;
                    $error = "Failed setting up table inserts & updates.";

                    goto end;
                }

                if ($mysqli->query($sqlQuery) !== TRUE)
                {
                    $status = false;
                    goto end;
                }
            }
            catch(Exception $e)
            {
                $status = false;
                $error = 'exception: ' . $e;
            }

            end:

            $out = array('data' => $result, 'status' => $status, 'error' => $error);

            return $out;    

        }

        function addColumn($name, $type, $metadata)
        {
            $status = true;
            $error = "";
            $result = "";

            $savedSchema = array();

            // escape name
            if ($name == "uuid")
                $name = "\"uuid\"";

            for ($i = 0; $i < count($this->table_schema); $i++)
            {
                $currentColumn = $this->table_schema[$i];
                if ($currentColumn["name"] == $name)
                {
                    $error = "Duplicate column name";
                    $status = false;
                    goto end;
                }
            }


            $topId = 0;
            for ($i = 0; $i < count($this->table_schema); $i++)
            {
                $currentColumn = $this->table_schema[$i];
                $currentId = $currentColumn["column_id"];
                if ($currentId > $topId)
                {
                    $topId = $currentId;
                }
            }

            $topId++;
            $insertAfter = null;
            if (count($this->table_schema) > 0)
                $insertAfter = $this->table_schema[count($this->table_schema) - 1];

            $dbType = $this->typeMap[$type];

            ////
            // save a copy & update schema to remove the column
            $savedSchema = array_merge([], $this->table_schema);

            unset($metadata['name']);
            unset($metadata['type']);

            $column = array_merge(array("name" => $name, "type" => $type, "column_id" => $topId), $metadata);
            array_push($this->table_schema, $column);

            $tableName = $this->_getInternalTableName();

            $internalColumnName = "column_" . $topId;
            try
            { 
                $this->sqlConnection->begin_transaction();

                $sqlQuery = "ALTER TABLE `" . $tableName . "` ADD COLUMN `" . $internalColumnName . "` " . $dbType . " NULL";
                if (null != $insertAfter)
                {
                    $insertAfterInternalName = "column_" . $insertAfter["column_id"];
                    $sqlQuery = $sqlQuery . " AFTER `" . $insertAfterInternalName . "`";
                }
                
                $sqlQuery = $sqlQuery . ";";

                if ($this->sqlConnection->query($sqlQuery) !== TRUE)
                {
                    $this->sqlConnection->rollback();
                    $status = false;
                    goto end;
                }

                $out = $this->_updateUpsertProc();
                if ($out["status"] != true)
                {
                    $status = false;
                    $error = $out["error"];
                    $result = $out["data"];

                    $this->sqlConnection->rollback();

                    goto end;
                }

                $this->_saveTableSchema();

                $this->sqlConnection->commit();
            }
            catch(Exception $e)
            {
                $status = false;
                $error = 'exception: ' . $e;

                goto end;
            }


            if ($status === false)
            {
                // restore the saved state
                $this->table_schema = array_merge([], $savedSchema);
            }

            end:

            $out = array('data' => $result, 'status' => $status, 'error' => $error);

            return $out;    
        }


        function deleteColumn($columnIndex)
        {
            $status = true;
            $error = "";
            $result = "";
            $savedSchema = array();

            $column = null;
            
            if ($columnIndex < 0 || $columnIndex >= count($this->table_schema))
            {
                $status = false;
                $error = "Invalid column index.";

                goto end;
            }

            $column = $this->table_schema[$columnIndex];

            $columnDBIndex = $column["column_id"];
            $columnInternalName = "column_" . $columnDBIndex;

            ////
            // save a copy & update schema to remove the column
            $savedSchema = array_merge([], $this->table_schema);

            array_splice($this->table_schema, $columnIndex, 1);

            $tableName = $this->_getInternalTableName();

            try
            { 
                $this->sqlConnection->begin_transaction();

                $sqlQuery = "ALTER TABLE `" . $tableName . "` DROP COLUMN `" . $columnInternalName . "`;";
                if ($this->sqlConnection->query($sqlQuery) !== TRUE)
                {
                    $this->sqlConnection->rollback();
                    $status = false;
                    goto end;
                }

                $out = $this->_updateUpsertProc();
                if ($out["status"] != true)
                {
                    $status = false;
                    $error = $out["error"];
                    $result = $out["data"];

                    $this->sqlConnection->rollback();

                    goto end;
                }

                $this->_saveTableSchema();

                $this->sqlConnection->commit();
            }
            catch(Exception $e)
            {
                $status = false;
                $error = 'exception: ' . $e;

                goto end;
            }


            if ($status === false)
            {
                // restore the saved state
                $this->table_schema = array_merge([], $savedSchema);
            }

            end:

            $out = array('data' => $result, 'status' => $status, 'error' => $error);

            return $out;    
        }

        function updateColumn($columnIndex, $type, $metadata)
        {
            $status = true;
            $error = "";
            $result = "";

            if ($columnIndex < 0 || $columnIndex >= count($this->table_schema))
            {
                $status = false;
                $error = "Invalid column index.";

                goto end;
            }

            $current_column_metadata = $this->table_schema[$columnIndex];

            $isInTrans = false;
            try
            {
                $this->sqlConnection->begin_transaction();
                $isInTrans = true;                
                                
                if ($current_column_metadata["type"] != $type)
                {                 
                    $inner_result = $this->changeColumnType($columnIndex, $type);
                    if ($inner_result['status'] == false)
                    {
                        $status = false;
                        $error = $inner_result['error'];
                        if ($error == '')
                            $error = 'Cannot change column type. Some records are not compatible with the new type.';
                        $this->sqlConnection->rollback();
                        goto end;
                    }
                }

                unset($metadata['columnd_id']);
    
                $newMetadata = array_merge(array("name" => $metadata["name"], "type" => $metadata["type"], "column_id" => $current_column_metadata['column_id']), $metadata);
                $this->table_schema[$columnIndex] = $newMetadata;
    
                $inner_result = $this->_saveTableSchema();
                if ($inner_result['status'] == false)
                {
                    $status = false;
                    $error = $inner_result['error'];
                    $this->sqlConnection->rollback();
                    goto end;
                }


                $this->sqlConnection->commit();
                $isInTrans = false;
            }
            catch(Exception $e)
            {                
                if ($isInTrans == true)
                {
                    try
                    {         
                        $this->sqlConnection->rollback();
                    }
                    catch(Exception $e)
                    {                        
                    }
                }

                $status = false;
                $error = 'exception: ' . $e;
            }

            end:

            if ($status == false)
            {
                // revert to the previous value
                $this->table_schema[$columnIndex] = $current_column_metadata;
            }

            $out = array('data' => "", 'status' => $status, 'error' => $error);

            return $out;                
        }

        function changeColumnType($columnIndex, $externalType)
        {
            $status = true;
            $error = "";
            $result = "";
            $savedSchema = array();

            if ($columnIndex < 0 || $columnIndex >= count($this->table_schema))
            {
                $status = false;
                $error = "Invalid column index.";

                goto end;
            }

            $tableName = $this->_getInternalTableName();

            $column = $this->table_schema[$columnIndex];
            if ($column == null)
            {
                $status = false;
                $error = "Column does not exist.";

                goto end;
            }

            if (!isset($this->typeMap[$externalType]))
            {
                $status = false;
                $error = "Invalid type " . $externalType;

                goto end;
            }

            $newInternalType = $this->typeMap[$externalType];

            $columnDBIndex = $column["column_id"];
            $columnInternalName = "column_" . $columnDBIndex;

            $isInTrans = false;

            // save the internal state            
            $savedSchema = array_merge(array(), $this->table_schema);

            // update the internal state
            $this->table_schema[$columnIndex]["type"] = $externalType;
 
            try
            { 
                $sqlQuery = "ALTER TABLE `" . $tableName . "` CHANGE COLUMN `" . $columnInternalName . "`  `" . $columnInternalName . "` " . $newInternalType . " NULL DEFAULT NULL;";

                $this->sqlConnection->begin_transaction();

                $isInTrans = true;

                $res = $this->sqlConnection->query($sqlQuery);
                if ($res !== TRUE)
                {
                    $status = false;
                    $this->sqlConnection->rollback();
                    $isInTrans = false;

                    goto end;
                }

                $out = $this->_updateUpsertProc();
                if ($out["status"] != true)
                {
                    $status = false;
                    $error = $out["error"];
                    $result = $out["data"];

                    $this->sqlConnection->rollback();
                    $isInTrans = false;

                    goto end;
                }

                $this->_saveTableSchema();

                $this->sqlConnection->commit();

                $isInTrans = false;
            }
            catch(Exception $e)
            {            
                if ($isInTrans == true)
                {
                    try
                    {         
                        $this->sqlConnection->rollback();
                    }
                    catch(Exception $e)
                    {                        
                    }
                }

                $status = false;
                $error = 'exception: ' . $e;
            }

            end:

            if ($status == false)
            {
                // restore original internal state
                $this->table_schema = array_merge([], $savedSchema);
            }

            $out = array('data' => "", 'status' => $status, 'error' => $error);

            return $out;                
        }

        function setColumnPosition($currentPosition, $position)
        {            
            $status = true;
            $error = "";
            $result = "";

            $savedSchema = array();

            if ($currentPosition < 0 || $currentPosition >= count($this->table_schema))
            {
                $status = false;
                $error = "Invalid current column position.";

                goto end;
            }

            if ($position < 0 || $position >= count($this->table_schema))
            {
                $status = false;
                $error = "Invalid column position.";

                goto end;
            }


            $tableName = $this->_getInternalTableName();

            if ($currentPosition == $position)
            {
                // same position. do nothing
                goto end;
            }

            $column = $this->table_schema[$currentPosition];
            $columnDBIndex = $column["column_id"];
            $columnInternalName = "column_" . $columnDBIndex;
            $columnDBType = $this->typeMap[$column["type"]];

            $isInTrans = false;

            // save the internal state
            $savedSchema = array_merge([], $this->table_schema);

            // update the internal state
            array_splice($this->table_schema, $currentPosition, 1);
            array_splice($this->table_schema, $position, 0, "placeholder");
            $this->table_schema[$position] = $column;

            try
            { 
                $sqlQuery = "ALTER TABLE `" . $tableName . "` CHANGE COLUMN `" . $columnInternalName . "`  `" . $columnInternalName . "` " . $columnDBType . " NULL DEFAULT NULL";

                if ($position == 0)                
                {
                    $sqlQuery = $sqlQuery . " AFTER `uuid`;";
                }
                else
                {
                    $prevColumnInternalName = "column_" . $this->table_schema[$position - 1]["column_id"];
                    $sqlQuery = $sqlQuery . " AFTER `" . $prevColumnInternalName . "`;";
                }

                $this->sqlConnection->begin_transaction();

                $isInTrans = true;

                $res = $this->sqlConnection->query($sqlQuery);
                if ($res !== TRUE)
                {
                    $status = false;
                    $this->sqlConnection->rollback();
                    $isInTrans = false;

                    goto end;
                }

                $out = $this->_updateUpsertProc();
                if ($out["status"] != true)
                {
                    $status = false;
                    $error = $out["error"];
                    $result = $out["data"];

                    $this->sqlConnection->rollback();
                    $isInTrans = false;

                    goto end;
                }

                $this->_saveTableSchema();

                $this->sqlConnection->commit();

                $isInTrans = false;
            }
            catch(Exception $e)
            {                
                if ($isInTrans == true)
                {
                    try
                    {         
                        $this->sqlConnection->rollback();
                    }
                    catch(Exception $e)
                    {                        
                    }
                }

                $status = false;
                $error = 'exception: ' . $e;
            }

            end:

            if ($status == false)
            {
                // restore original internal state
                $this->table_schema = array_merge([], $savedSchema);
            }

            $out = array('data' => "", 'status' => $status, 'error' => $error);

            return $out;                
        }
    }

?>