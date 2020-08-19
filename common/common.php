<?php	

    function generic_error_handler($errno, $err)
    {
        throw new Exception($err);
    }

    function guid()
    {
        if (function_exists('com_create_guid') === true)
            return trim(com_create_guid(), '{}');
        
        $data = openssl_random_pseudo_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    function test_folder_write_permissions($folderPath)
    {
        try
        {
            $file = fopen($folderPath . "/log.txt", "wt+");
            if (false == $file)
            {
                return false;
            }
            
            fputs($file, "foo");
            fclose($file);
            unlink($folderPath . "/log.txt");
        }
        catch(Exception $e)
        {
            return false;
        }
        
        return true;
    }

    function is_local_host($whitelist = ['127.0.0.1', '::1']) {
        return in_array($_SERVER['REMOTE_ADDR'], $whitelist);
    }

    function invoke_fn($instance, $params)
    {
        $decoded_params = json_decode($params, true);
        
        $cmd = $decoded_params["cmd"];
        $args = $decoded_params["params"];

        $status = true;
        $result = "";
        $error = "";
        
        try
        {
            if ($instance)
                $inner_result = call_user_func_array(array($instance, $cmd), $args);
            else
                $inner_result = call_user_func_array($cmd, $args);

            $result = $inner_result["data"];
            $status = $inner_result["status"];
            $error = $inner_result["error"];
        }
        catch(Exception $e)
        {
            $error = "Exception processing command " . $cmd;
            $status = false;
            $result = "";
        }

        $out = array('data' => $result, 'status' => $status, 'error' => $error);

        return $out;
    }
	
?>