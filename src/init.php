<?php
namespace booosta\dataobjects;

\booosta\Framework::add_module_trait('base', 'dataobjects\base');
\booosta\Framework::add_module_trait('webapp', 'dataobjects\webapp');

trait base
{
  protected function makeDataobject($table, $init = null)
  {
    if(strtolower($table) == 'classdefiner') return 'Error: "Classdefiner" is a reserved name and cannot be used as class name';
    if($table == '') return null;

    $add_to_cache = false;
    $cache_hit = false;

    if(!class_exists("\\booosta\\dataobjects\\$table")):
      // look into cache
      if($this->config('use_dataobject_cache')):
        $cache_dir = $this->real_basedir() . '/local/dataobjectcache/';
        if(!is_dir($cache_dir)) mkdir($cache_dir);

        if(is_readable("$cache_dir/$table.cache.php")):
          include_once("$cache_dir/$table.cache.php");
          $cache_hit = true;
        else:
          $add_to_cache = true;
        endif;
      endif;

      if(!$cache_hit):
        $definer = $this->makeInstance("\\booosta\\dataobjects\\Classdefiner");
        $code = $definer->compose_classes($table);
        #\booosta\debug($code);
        eval($code);
      endif;

      if($add_to_cache) file_put_contents("$cache_dir/$table.cache.php", "<?php\n" . $code . "\n?".'>');
    endif;

    $obj = $this->makeInstance("\\booosta\\dataobjects\\$table", $init);
    return $obj;
  }

  protected function getDataobjects($table, $clause = null, $order = '1', $limit = null)
  {
    if($table == '') return null;
    $result = [];

    if($clause === null) $clause = '0=0';
    if($limit !== null) $limitstr = "limit $limit"; else $limitstr = '';
    $rows = $this->DB->query_arrays("select * from `$table` where $clause order by $order $limitstr");
    #\booosta\debug($rows);
    #\booosta\debug("select * from `$table` where $clause order by $order $limitstr");

    if($rows[0]['ser__obj']):
      $unserialize_func = $this->config('unserialize_func');
      if($unserialize_func == '') $unserialize_func = 'unserialize';

      foreach($rows as $row) $result[] = $this->makeDataobject($table, $unserialize_func($row['ser__obj']));
    endif;

    foreach($rows as $row) $result[] = $this->makeDataobject($table, $row);
    return $result;
  }

  protected function getDataobject($table, $clause = '0=1', $create = false)
  {
    if($table == '') return null;

    $obj = $this->makeDataobject($table);
    $success = $obj->load($clause);
    if($success) return $obj;
 
    if($create) return $this->makeDataobject($table);
    return null;
  }
}

trait webapp
{
  protected function chkDataobject($obj, $err = 'Databobject is invalid')
  {
    if(!is_object($obj) || !$obj->is_valid()) $this->raise_error($err);
  }
}
