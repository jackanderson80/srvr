<?php

    class ResourceManager
    {
        var $sqlConnection = null;   

        function __construct($sqlConnection)
        {
            $this->sqlConnection = $sqlConnection;
            $this->tenants = [];
            $this->nodes = [];
        }

        private function _free_result()
        {
            while ($this->sqlConnection && mysqli_more_results($this->sqlConnection) && mysqli_next_result($this->sqlConnection)) {
    
                $dummyResult = mysqli_use_result($this->sqlConnection);
    
                if ($dummyResult instanceof mysqli_result) {
                    mysqli_free_result($this->sqlConnection);
                }
            }
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

        function addNode($name, $uri, $credentials, $ram, $disk)
        {
            $status = true;
            $error = "";
            $result = "";
        
            $procName = "prc_AddNode";
            $statement = "CALL " . $procName . "(?, ?, ?, ?, ?)";
            
            try
            {     
                if (!($stmp = mysqli_prepare($this->sqlConnection, $statement)))
                {
                    $status = false;
                    
                    $error = "Internal error. Prepare failed: (" . $this->sqlConnection->errno . ") " . $this->sqlConnection->error;
                    goto end;
                }

                $params = [];
 
                array_push($params, "sssii");
                array_push($params, $name);
                array_push($params, $uri);
                array_push($params, $credentials);
                array_push($params, $ram);
                array_push($params, $disk);

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

                $this->_free_result();
            }
            catch(Exception $e)
            {
                $error = "Exception: " . $e;
                $status = false;
                $result = "";
            }


            $this->getNodes();

            end:

            $out = array('data' => $result, 'status' => $status, 'error' => $error);

            return $out;
        }

        function deleteNode($node_uuid)
        {
            $status = true;
            $error = "";
            $result = "";

            // refresh the nodes & tenants lists
            $inner_result = $this->getNodes();
            if ($inner_result["status"] == false)
            {
                $error = "Cannot get nodes list";
                $status = false;
                goto end;
            }

            $inner_result = $this->getTenants();
            if ($inner_result["status"] == false)
            {
                $error = "Cannot get tenants list";
                $status = false;
                goto end;
            }

            foreach ($this->tenants as $key => $value)
            {
                if ($value["node_uuid"] == $node_uuid)
                {
                    $status = false;
                    $error = "The node cannot be deleted because it has tenants associated with it.";
                    goto end;
                }
            }

            $status = true;
            $error = "";
            $result = "";
        
            $procName = "prc_DeleteNode";
            $statement = "CALL " . $procName . "(?)";
            
            try
            {                   
                if (!($stmp = mysqli_prepare($this->sqlConnection, $statement)))
                {
                    $status = false;
                    
                    $error = "Internal error. Prepare failed: (" . $this->sqlConnection->errno . ") " . $this->sqlConnection->error;
                    goto end;
                }

                $params = [];
 
                array_push($params, "s");
                array_push($params, $node_uuid);
                
                call_user_func_array(array($stmp, 'bind_param'), $this->_refValues($params));

                if (!$stmp->execute())
                {
                    $status = false;
                    $error = $stmp->error;
                    goto end;
                }

                $stmp->bind_result($deleted_count);
                
                while ($stmp->fetch())
                {
                    $result = array(
                        "deleted_count" => $deleted_count
                    );
                }

                $this->_free_result();
            }
            catch(Exception $e)
            {
                $error = "Exception: " . $e;
                $status = false;
                $result = "";
            }

            // refresh the nodes & tenants lists
            $inner_result = $this->getNodes();
            if ($inner_result["status"] == false)
            {
                $error = "Cannot get nodes list";
                $status = false;
                goto end;
            }

            $inner_result = $this->getTenants();
            if ($inner_result["status"] == false)
            {
                $error = "Cannot get tenants list";
                $status = false;
                goto end;
            }

            end:

            $out = array('data' => $result, 'status' => $status, 'error' => $error);

            return $out;
        }

        function addTenant($node_uuid, $tenantType)
        {           
            $status = true;
            $error = "";
            $result = "";

            // refresh the tenants lists
            $inner_result = $this->getTenants();
            if ($inner_result["status"] == false)
            {
                $error = "Cannot get tenants list";
                $status = false;
                goto end;
            }
        
            $procName = "prc_addTenant";
            $statement = "CALL " . $procName . "(?, ?)";
            
            try
            {             
                if (!($stmp = mysqli_prepare($this->sqlConnection, $statement)))
                {
                    $status = false;
                    
                    $error = "Internal error. Prepare failed: (" . $this->sqlConnection->errno . ") " . $this->sqlConnection->error;
                    goto end;
                }

                $params = [];
 
                array_push($params, "ss");
                array_push($params, $node_uuid);
                array_push($params, $tenantType);
                
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

                $this->_free_result();
            }
            catch(Exception $e)
            {
                $error = "Exception: " . $e;
                $status = false;
                $result = "";
            }

            // refresh the tenants lists
            $inner_result = $this->getTenants();
            if ($inner_result["status"] == false)
            {
                $error = "Cannot get tenants list";
                $status = false;
                goto end;
            }

            end:

            $out = array('data' => $result, 'status' => $status, 'error' => $error);

            return $out;
        }
        
        function createTenant($tenantType)
        {
            $status = true;
            $error = "";
            $result = "";

            $innerResult = $this->findNode($tenantType);
            if ($innerResult["status"] == false)
            {
                $error = "Cannot find availble node";
                $status = false;
                goto end;
            }

            $node_uuid = $innerResult["data"]["node_uuid"];

            if ($tenantType != 'WorkspaceDB')
            {
                $error = "The requested type is not implemented";
                $status = false;
                goto end;
            }

            try
            {
                $nodeInfo = null;
                foreach ($this->nodes as $key => $value)
                {
                    if ($key == $node_uuid)
                    {
                        $nodeInfo = $value;
                        break;
                    }
                }

                $credentials = json_decode($nodeInfo["credentials"], true);
                if ($credentials == null)
                {
                    $error = "Invalid node credentials";
                    $status = false;
                    goto end;
                }

                $tenant_uuid = guid();
                $add_tenant_result = $this->registerTenant($tenant_uuid, $node_uuid, $tenantType);
                if ($add_tenant_result["status"] == false)
                {
                    $error = "Cannot add tenant metadata";
                    $status = false;
                    goto end;
                }

                $ws_db_name = $tenantType . '-' . $tenant_uuid;
                $db_create_result = db_create($credentials["hostname"], $credentials["username"], $credentials["password"], $ws_db_name, $credentials["port"]);
                if ($db_create_result == false)
                {
                    $this->deleteTenant($tenant_uuid);

                    $error = "Cannot create tenant";
                    $status = false;
                    goto end;
                }

                $tenant_activation_state = $this->setTenantActiveState($tenant_uuid, true);
                if ($tenant_activation_state["status"] == false)
                {
                    db_delete($credentials["hostname"], $credentials["username"], $credentials["password"], $ws_db_name, $credentials["port"]);

                    $error = "Cannot activate new tenant";
                    $status = false;
                    goto end;
                }
            }
            catch(Exception $e)
            {
                $error = "Exception: " . $e;
                $status = false;
                goto end;
        }

            end:

            $out = array('data' => $result, 'status' => $status, 'error' => $error);

            return $out;
        }


        function registerTenant($tenant_uuid, $node_uuid, $tenantType)
        {
            $status = true;
            $error = "";
            $result = "";
        
            $procName = "prc_AddTenant";
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
                array_push($params, $tenant_uuid);
                array_push($params, $node_uuid);
                array_push($params, $tenantType);
                
                call_user_func_array(array($stmp, 'bind_param'), $this->_refValues($params));

                if (!$stmp->execute())
                {
                    $status = false;
                    $error = $stmp->error;
                    goto end;
                }

                $this->_free_result();
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


        function deleteTenant($tenant_uuid)
        {
            $status = true;
            $error = "";
            $result = "";

            // refresh the tenants lists
            $inner_result = $this->getTenants();
            if ($inner_result["status"] == false)
            {
                $error = "Cannot get tenants list";
                $status = false;
                goto end;
            }
        
            $procName = "prc_DeleteTenant";
            $statement = "CALL " . $procName . "(?)";
            
            try
            {             
                if (!($stmp = mysqli_prepare($this->sqlConnection, $statement)))
                {
                    $status = false;
                    
                    $error = "Internal error. Prepare failed: (" . $this->sqlConnection->errno . ") " . $this->sqlConnection->error;
                    goto end;
                }

                $params = [];
 
                array_push($params, "s");
                array_push($params, $tenant_uuid);
                
                call_user_func_array(array($stmp, 'bind_param'), $this->_refValues($params));

                if (!$stmp->execute())
                {
                    $status = false;
                    $error = $stmp->error;
                    goto end;
                }

                $stmp->bind_result($deleted_count);
                
                while ($stmp->fetch())
                {
                    $result = array(
                        "deleted_count" => $deleted_count
                    );
                }

                $this->_free_result();
            }
            catch(Exception $e)
            {
                $error = "Exception: " . $e;
                $status = false;
                $result = "";
            }

            // refresh the tenants lists
            $inner_result = $this->getTenants();
            if ($inner_result["status"] == false)
            {
                $error = "Cannot get tenants list";
                $status = false;
                goto end;
            }


            end:

            $out = array('data' => $result, 'status' => $status, 'error' => $error);

            return $out;
        }

        function setTenantActiveState($tenant_uuid, $isActive)
        {
            $status = true;
            $error = "";
            $result = "";

            // refresh the tenants lists
            $inner_result = $this->getTenants();
            if ($inner_result["status"] == false)
            {
                $error = "Cannot get tenants list";
                $status = false;
                goto end;
            }
        
            $procName = "prc_SetTenantActiveState";
            $statement = "CALL " . $procName . "(?, ?)";
            
            try
            {             
                if (!($stmp = mysqli_prepare($this->sqlConnection, $statement)))
                {
                    $status = false;
                    
                    $error = "Internal error. Prepare failed: (" . $this->sqlConnection->errno . ") " . $this->sqlConnection->error;
                    goto end;
                }

                $params = [];
 
                array_push($params, "si");
                array_push($params, $tenant_uuid);
                array_push($params, $isActive == true || $isActive == 1);
                
                call_user_func_array(array($stmp, 'bind_param'), $this->_refValues($params));

                if (!$stmp->execute())
                {
                    $status = false;
                    $error = $stmp->error;
                    goto end;
                }

                $this->_free_result();
            }
            catch(Exception $e)
            {
                $error = "Exception: " . $e;
                $status = false;
                $result = "";
            }

            // refresh the tenants lists
            $inner_result = $this->getTenants();
            if ($inner_result["status"] == false)
            {
                $error = "Cannot get tenants list";
                $status = false;
                goto end;
            }

            end:

            $out = array('data' => $result, 'status' => $status, 'error' => $error);

            return $out;
        }

        
        function findFreeTenant($tenantType)
        {
            $status = true;
            $error = "";
            $result = "";

            $inner_result = $this->getTenants();
            if ($inner_result["status"] == false)
            {
                $status = false;
                $error = $inner_result["error"];
                goto end;
            }

            $data = $inner_result["data"];

            $selectedKey = null;
            foreach($data as $key => $value)
            {
                if ($value["type"] != $tenantType || $value["is_active"] != 1)
                    continue;

                if ($selectedKey == null || $value["disk_usage"] < $data[$selectedKey]["disk_usage"])
                {
                    $selectedKey = $key;
                }
            }

            if ($selectedKey == null)
            {
                $status = false;
                $error = "Cannot find available tenant";
                goto end;
            }

            $result = $data[$selectedKey];

            end:

            $out = array('data' => $result, 'status' => $status, 'error' => $error);

            return $out;

        }

        function getTenants()
        {
            $status = true;
            $error = "";
            $result = "";
        
            $procName = "prc_GetTenants";
            $statement = "CALL " . $procName . "()";
            
            try
            {                   
                //$query = "	SELECT t.uuid, t.type, t.disk_usage, t.is_active, n.uuid as 'node_uuid', n.uri, n.parameters FROM tenant t INNER JOIN node n on n.uuid = t.node_uuid";
                if (!($stmp = mysqli_prepare($this->sqlConnection, $statement)))
                {
                    $status = false;
                    
                    $error = "Internal error. Prepare failed: (" . $this->sqlConnection->errno . ") " . $this->sqlConnection->error;
                    goto end;
                }

                $params = [];
 
                if (!$stmp->execute())
                {
                    $status = false;
                    $error = $stmp->error;
                    goto end;
                }

                $stmp->bind_result($tenant_uuid, $tenant_type, $tenant_disk_usage, $tenant_isactive, $node_uuid, $node_uri, $node_credentials);
                
                $result = [];
                while ($stmp->fetch())
                {
                    $record = array(
                        "tenant_uuid" => $tenant_uuid,
                        "type" => $tenant_type,
                        "disk_usage" => $tenant_disk_usage,
                        "is_active" => $tenant_isactive,
                        "node_uuid" => $node_uuid,
                        "node_uri" => $node_uri,
                        "node_credentials" => $node_credentials
                    );

                    $result[$tenant_uuid] = $record;
                }

            }
            catch(Exception $e)
            {
                $error = "Exception: " . $e;
                $status = false;
                $result = "";
            }

            if ($status == true)
            {
                $this->tenants = $result;
            }

            end:

            $out = array('data' => $result, 'status' => $status, 'error' => $error);

            return $out;

        }

        function getNodes()
        {
            $status = true;
            $error = "";
            $result = "";
        
            $procName = "prc_GetNodes";
            $statement = "CALL " . $procName . "";
            
            try
            {                   
                if (!($stmp = mysqli_prepare($this->sqlConnection, $statement)))
                {
                    $status = false;
                    
                    $error = "Internal error. Prepare failed: (" . $this->sqlConnection->errno . ") " . $this->sqlConnection->error;
                    goto end;
                }

                $params = [];
 
                if (!$stmp->execute())
                {
                    $status = false;
                    $error = $stmp->error;
                    goto end;
                }

                $stmp->bind_result($node_uuid, $name, $node_uri, $node_credentials, $node_ram, $node_disk);
                
                $result = [];
                while ($stmp->fetch())
                {
                    $record = array(
                        "uuid" => $node_uuid,
                        "name" => $name,
                        "uri" => $node_uri,
                        "credentials" => $node_credentials,
                        "ram" => $node_ram,
                        "disk" => $node_disk
                    );

                    $result[$node_uuid] = $record;
                }

            }
            catch(Exception $e)
            {
                $error = "Exception: " . $e;
                $status = false;
                $result = "";
            }

            if ($status == true)
            {
                $this->nodes = $result;
            }

            end:

            $out = array('data' => $result, 'status' => $status, 'error' => $error);

            return $out;
        }

        function findNode($tenantType)
        {
            $status = true;
            $error = "";
            $result = "";

            $this->getNodes();

            $node_tenant_counts = [];

            foreach ($this->nodes as $key => $value)
            {
                $node_tenant_counts[$key] = 0;
            }

            foreach ($this->tenants as $key => $tenant)
            {
                $node_tenant_counts[$tenant["node_uuid"]]++;
            }
            
            $node_uuid = null;

            $minCount = PHP_INT_MAX;
            foreach ($node_tenant_counts as $key => $value)
            {
                if ($value < $minCount)
                {
                    $node_uuid = $key;
                    $minCount = $value;
                }
            }

            end:

            if ($node_uuid != null)
            {
                $result = array("node_uuid" => $node_uuid);
            }
            else
            {
                $error = "No available nodes";
                $status = false;
            }

            $out = array('data' => $result, 'status' => $status, 'error' => $error);

            return $out;
        }
    }

    ?>