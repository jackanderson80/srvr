<?php
    require_once __DIR__ . "/../common/db_common.php";

    $templates = [
        array(
            "name" => "default_table_template",
            "data" =>  array(
                "schema" => array(
                    array(
                        "name" => "Customer name",
                        "type" => "text",
                        "sortable" => true,
                        "filterable" => true,
                        "resizable" => true,
                        "align" => 'left',
                        "color" => '#FFFFFF',
                        "decimalPlaces" => 2,
                        "thousandsSeparator" => ',',
                        "decimalSeparator" => '.',
                        "minValue" => 0,
                        "maxValue" => 1000000,
                        "minLength" => 0,
                        "maxLength" => 250,
                        "minDateTime" => "2000-01-01",
                        "maxDateTime" => "2030-01-01"
                    ),
                    array(
                        "name" => "Contact Person",
                        "type" => "text",
                        "sortable" => true,
                        "filterable" => true,
                        "resizable" => true,
                        "align" => 'left',
                        "color" => '#FFFFFF',
                        "decimalPlaces" => 2,
                        "thousandsSeparator" => ',',
                        "decimalSeparator" => '.',
                        "minValue" => 0,
                        "maxValue" => 1000000,
                        "minLength" => 0,
                        "maxLength" => 100,
                        "minDateTime" => "2000-01-01",
                        "maxDateTime" => "2030-01-01"
                    ),
                    array(
                        "name" => "Purchase date",
                        "type" => "datetime",
                        "sortable" => true,
                        "filterable" => true,
                        "resizable" => true,
                        "align" => 'left',
                        "color" => '#FFFFFF',
                        "decimalPlaces" => 2,
                        "thousandsSeparator" => ',',
                        "decimalSeparator" => '.',
                        "minValue" => 0,
                        "maxValue" => 1000000,
                        "minLength" => 0,
                        "maxLength" => 100,
                        "minDateTime" => "2000-01-01",
                        "maxDateTime" => "2030-01-01"
                    ),
                    array(
                        "name" => "Notes",
                        "type" => "text",
                        "sortable" => true,
                        "filterable" => true,
                        "resizable" => true,
                        "align" => 'left',
                        "color" => '#FFFFFF',
                        "decimalPlaces" => 2,
                        "thousandsSeparator" => ',',
                        "decimalSeparator" => '.',
                        "minValue" => 0,
                        "maxValue" => 1000000,
                        "minLength" => 0,
                        "maxLength" => 100,
                        "minDateTime" => "2000-01-01",
                        "maxDateTime" => "2030-01-01"
                    )
                ),
                "rows" => array(

                )
            )
        )
    ];

    function create_table_from_template($tableManager, $tableName, $templateName)
    {
        global $templates;

        $status = true;
        $error = "";
        $data = array();

        $template = null;
        for ($i = 0; $i < count($templates) && $template == null; $i++)
        {
            if ($templates[$i]["name"] == $templateName)
                $template = $templates[$i];
        }

        if ($template == null)
        {
            $status = false;
            $error = "Invalid template name";
            goto end;
        }

		$inner_output = $tableManager->createTable($tableName, $template["data"]["schema"]);
        if ($inner_output['status'] == false)
        {
            $status = false;
            $error = $inner_output["error"];
            goto end;
        }

        $data["uuid"] = $inner_output["data"]["uuid"];

        end:

        $output = array('status' => $status, 'error' => $error, 'data' => $data);

        return $output;
    }

?>