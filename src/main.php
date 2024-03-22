<?php
namespace booosta\dataobjects;

\booosta\Framework::init_module('dataobjects');

class Tableclass extends \booosta\base\Base
{
  protected $data = [];
  protected $error;
  protected $datefields = [];

  public function getr($var, $index = null)
  {
    if($index === null):
      $result = $this->data[$var];
    else:
      if(is_array($this->data[$var])):
        $tmp = $this->data[$var];
        $result = $tmp[$index];
      else:
        return null;
      endif;
    endif;

    return $result;
  }

  public function get($var, $index = null)
  {
    $result = $this->getr($var, $index);
    if(is_string($result) && $this->config('escape_curl')) return $this->escape_curl($result);

    return $result;
  }

  protected function escape_curl($code)
  {
    $code = str_replace('{', '&#123;', $code);
    $code = str_replace('}', '&#125;', $code);

    return $code;
  }

  public function set($var, $val, $array_val = '__not_set__', $replace_lt = false)
  {
    if(is_string($val) && $replace_lt) $val = str_replace('<', '&lt;', $val);

    if(isset($this->data[$var]) && is_array($this->data[$var]) && !is_array($val)):
      if($array_val !== '__not_set__'):
        $tmp = $this->data[$var];
        $tmp[$val] = $array_val;  // $val is the array index in this case
        $this->data[$var] = $tmp;
      else:
        array_push($this->data[$var], $val);
      endif;
    elseif((!isset($this->data[$var]) || !is_array($this->data[$var])) && !is_array($val) && $array_val !== '__not_set__'):   // create new $this->data[$var]
       $tmp = array($val => $array_val);  // $val is the array index in this case
       $this->data[$var] = $tmp;
    else:
      $this->data[$var] = $val;
    endif;

    $this->validate_();
  }

  public function del($var, $index = null)
  {
    if($index === null):
      unset($this->data[$var]);
    else:
      if(is_array($this->data[$var])):
        $tmp = $this->data[$var];
        unset($tmp[$index]);
        $this->data[$var] = $tmp;
      endif;
    endif;
  }

  public function get_data() { return $this->data; }

  public function set_data($data, $empty2null = false)
  {
    if(is_array($data)) $this->data = $data;
    if($empty2null) $this->empty2null();

    $this->validate_();
  }

  public function merge_data($data, $empty2null = false)
  {
    if(is_array($data)) $this->data = array_merge($this->data, $data);
    if($empty2null) $this->empty2null();
    $this->validate_();
  }

  public function empty2null()
  {
    array_walk($this->data, [$this, 'null_if_empty']);
  }

  protected function null_if_empty(&$item, $key)
  {
    if($item === '' && in_array($key, $this->nullfields)) $item = null;
  }

  public function quote_string($str) { return "'".str_replace("'", "\\'", $str)."'"; }
  public function get_error() { return $this->error; }

  protected function validate_()
  {
    foreach($this->datefields as $datefield)
      if($this->data[$datefield]) $this->data[$datefield] = date('Y-m-d', strtotime(str_replace(' ', '', $this->data[$datefield])));
      else $this->data[$datefield] = null;

    $this->validate();
  }

  protected function validate() {}
  public function set_datefields($val) { $this->datefields = $val; }
  public function add_datefield($val) { $this->datefields[] = $val; }
} 


class Classdefiner extends \booosta\base\Base
{
  public function compose_classes($tablenames = null)
  {
    $serialize_func = $this->config('serialize_func');
    if($serialize_func == '') $serialize_func = 'serialize';
    $unserialize_func = $this->config('unserialize_func');
    if($unserialize_func == '') $unserialize_func = 'unserialize';
 
    $ds = "namespace booosta\dataobjects; \n\n";
 
    $database = $this->config('db_database');
    $this->init_db();

    if($tablenames === null):
      $tables = $this->DB->DB_tablenames($database);
      if($this->config('fw_tables_only')) $tables = $this->config('fw_tables_only');;
    else:
      if(is_string($tablenames)) $tables = array($tablenames);
      else $tables = $tablenames;
    endif;
 
    foreach($tables as $tablename):
      $constructor_code = '';
      $allfieldlist = '';
      $rowidentifier = '';
      $is_flex = false;
      $keyfield = '';
      $serial_field_used = [];
      $commafix = '';
      $field_arr = []; $all_field_arr = []; $id_field_arr = [];
      $nullfields = [];
 
      $classname = "C__$tablename";
 
      $ds .= "class $classname extends Tableclass\n{\n";
  
      $serial_field = $this->config('serial_field');
      $constructor_code .= "    parent::__construct();\n";
      $constructor_code .= "    \$serial_field = \$this->config('serial_field');\n";
 
      $fields = $this->DB->DB_fields($database, $tablename);
      #\booosta\debug("$database, $tablename"); \booosta\debug($fields);
      foreach($fields as $field):
        $fieldname = $field->name;
 
        if($field->type == 'int'):
          $fieldfunction = 'intVal';
          $fieldtype = 'i';
        else:
          $fieldfunction = '$this->quote_string';
          $fieldtype = 's';
        endif;
  
        if($field->type == 'decimal')
          $commafix .= "if(strstr(\$this->data['$fieldname'], ',')) \$this->data['$fieldname'] = str_replace(',', '.', \$this->data['$fieldname']);\n";

        $allfieldlist .= "`$fieldname`, ";

        if($fieldname == 'ser__obj'):
          $is_flex = true;
          $field_arr['ser__obj'] = 's';
          $all_field_arr['ser__obj'] = 's';
          $field_values['ser__obj'] = '$ser_obj';
          continue;
        endif;
  
        if(!$field->autoval):
          $field_arr[$fieldname] = $fieldtype;
          $field_values[$fieldname] = "\$this->data['$fieldname']";
        endif;
  
        if(is_array($serial_field) && in_array($fieldname, $serial_field)):
          $serial_field_used[] = $fieldname;
          $fieldvar = "\".$fieldfunction(\$serial__$fieldname).\"";
          $field_values[$fieldname] = "\$serial__$fieldname";
        else:
          $fieldvar = "\".$fieldfunction(\$this->data['$fieldname']).\"";
        endif;
  
        $all_field_arr[$fieldname] = $fieldtype;
  
        if($field->default!='' && !$field->primarykey):
          $defaultstr = addcslashes($field->default, '"$');
          $constructor_code .= "    \$this->data['$fieldname'] = \"$defaultstr\";\n";
        endif;

        if($field->primarykey):
          $rowidentifier .= "$fieldname=$fieldvar and ";

          $id_field_arr[$fieldname] = $fieldtype;
          $field_values[$fieldname] = "\$this->data['$fieldname']";

          if($field->autoval) $keyfield = $fieldname;
          else $alt_keyfield = $fieldname;
        endif;

        if($field->null) $nullfields[] = $fieldname;
      endforeach;

      if($keyfield == '') $keyfield = $alt_keyfield;   // no autoincrement found, use the found primarykey

      $ds .= '  public $nullfields = [\'' . implode("','", $nullfields) . "'];\n\n";
  
      if(!$is_flex):
        $constructor_code .= "
    \$this->load_array(\$initarr, \$do_unserialize);";
      endif;

      $allfieldlist = substr($allfieldlist, 0, -2);
      $rowidentifier = substr($rowidentifier, 0, -5);
      $constructor_code .= "\n\$this->validate_();";
 
      $ds .= "  public function __construct(\$initarr = null, \$do_unserialize = true)\n  {\n";
      $ds .= $constructor_code . "\n  }\n\n";
 
      $ds .= "  public function is_valid()\n  {\n";
      if($keyfield) $ds .= "    return (isset(\$this->data['$keyfield']) && \$this->data['$keyfield']);\n  }\n\n";
      else $ds .= "    return true;\n  }\n\n";
 
      $ds .= "  public function insert(\$log = true)\n  {\n";
      $ds .= "    \$this->validate_();";
      if($is_flex) $ds .= "    \$ser_obj = addcslashes($serialize_func(\$this->data), \"'\");\n";
 
      if(is_array($this->config('serial_field')))
        foreach($this->config('serial_field') as $sf)
          if(in_array($sf, $serial_field_used))
            $ds .= "    \$serial__$sf = addcslashes($serialize_func(\$this->data['$sf']), \"'\");\n";
 
      $insert_statement = $this->DB->get_insert_statement($tablename, $field_arr, $field_values);
      $ds .= "
    $commafix
    $insert_statement

    if(\$error = \$this->DB->get_error()) \$this->error .= \$error;\n";
  
      if($keyfield != ''):
        $ds .= "    \$newid = \$this->DB->last_insert_id();
    \$this->data['$keyfield'] = \$newid;\n";
        if($is_flex) $ds .= "    \$res2 = \$this->update(\$log);\n";
        else $ds .= "    \$res2 = null;\n";
        $ds .= "    if(\$res == -1 || \$res2 == -1):\n";
        $ds .= "      return -1;\n";
        $ds .= "    endif;\n";
      else:
        $ds .= "    \$newid = true;\n";
        #$ds .= "\booosta\debug('newid true');\n";
      endif;
  
      $ds .= "    return \$newid;\n  }\n\n";
  
      $ds .= "  public function insertfull()\n  {\n";
      $ds .= "    \$this->validate_();";
      if($is_flex) $ds .= "    \$ser_obj = addcslashes($serialize_func(\$this->data), \"'\");\n";

      if(is_array($this->config('serial_field')))
        foreach($this->config('serial_field') as $sf)
          if(in_array($sf, $serial_field_used))
            $ds .= "    \$serial__$sf = addcslashes($serialize_func(\$this->data['$sf']), \"'\");\n";
 
      $insert_statement = $this->DB->get_insert_statement($tablename, $all_field_arr, $field_values);
      $ds .= "
    $commafix
    $insert_statement

    if(\$error = \$this->DB->get_error()) \$this->error .= \$error;\n";
 
      $ds .= "    return \$res;\n  }\n\n";
 
      $ds .= "  public function update(\$log = true)\n  {\n";
      $ds .= "    \$this->validate_();";
      $ds .= "    if(!\$this->is_valid()) return false;\n";
      if($is_flex) $ds .= "    \$ser_obj = addcslashes($serialize_func(\$this->data), \"'\");\n";
 
      if(is_array($this->config('serial_field')))
        foreach($this->config('serial_field') as $sf)
          if(in_array($sf, $serial_field_used))
            $ds .= "    \$serial__$sf = addcslashes($serialize_func(\$this->data['$sf']), \"'\");\n";
 
      $update_statement = $this->DB->get_update_statement($tablename, $field_arr, $field_values, $id_field_arr);

      $ds .= "
    $commafix
    $update_statement

    if(\$error = \$this->DB->get_error()) \$this->error .= \$error;\n";
 
      $ds .= "    return \$res;\n  }\n\n";
 
      $ds .= "  public function delete(\$log = true)\n  {\n";
      $sql = "delete from `$tablename` where $rowidentifier";
      $ds .= "    if(\$this->is_valid())\n";
      $ds .= "      \$result = \$this->DB->query(\"$sql\", \$log);
                    if(\$error = \$this->DB->get_error()) \$this->error .= \$error;
                    return \$result;\n  }\n\n";
 
      $ds .= "  public function save()\n  {
    if(isset(\$this->data['$keyfield']) && \$this->DB->query_value(\"select count(*) from `$tablename` where $rowidentifier\")):
      \$this->update();\n";
      if($keyfield) $ds .= "      return \$this->data['$keyfield'];\n";
      else $ds .= "      return true;\n";
      $ds .= "    else:\n      return \$this->insert();\n    endif;\n  }
 
  public function savefull()\n  {
    if(\$this->DB->query_value(\"select count(*) from `$tablename` where $rowidentifier\"))
      return \$this->update();
    else return \$this->insertfull();\n  }
 
  protected function load(\$search = null)
  {
    if(\$search == null) return false;
    if(is_numeric(\$search)) \$search = \"$keyfield='\$search'\";

    \$result = \$this->DB->query_list(\"select $allfieldlist from `$tablename` where \$search\");

    if(is_array(\$result) && sizeof(\$result)):";

      if($is_flex) $ds .= "
      \$this->data = $unserialize_func(\$result['ser__obj']);";
      else $ds .= "
      \$this->load_array(\$result, true);";

      $ds .= "
      return true;
    endif;

    return false;";

     $ds .= "
  }

  protected function load_array(\$initarr, \$do_unserialize)
  {
    \$serial_field = \$this->config('serial_field');

    if(is_array(\$initarr))
      foreach(\$initarr as \$name=>\$val):
        if(is_int(\$name)) continue;
        if(\$do_unserialize && is_array(\$serial_field) && in_array(\$name, \$serial_field))
          \$val = $unserialize_func(\$val);

        \$this->data[\$name] = \$val;
      endforeach;

      \$this->validate_();
  }
}
\n\n\n";
    endforeach;

    return $ds;
  }
}
