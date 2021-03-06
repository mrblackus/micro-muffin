<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Mathieu
 * Date: 08/06/13
 * Time: 15:59
 * To change this template use File | Settings | File Templates.
 */

namespace MicroMuffin\Models;

use MicroMuffin\PDOS;

abstract class Deletable extends Writable
{
  /**
   * Delete the models from the database
   * @return void
   */
  public function delete()
  {
    $class = strtolower(get_called_class());
    $table = self::$_table_name != null ? self::$_table_name : $class . 's';

    $pdo = PDOS::getInstance();

    $whereClause = '';
    foreach (static::$_primary_keys as $pk)
      $whereClause .= $pk . ' = :' . $pk . ' AND ';
    $whereClause = substr($whereClause, 0, -4);
    $sql         = 'DELETE FROM ' . $table . ' WHERE ' . $whereClause;
    $query       = $pdo->prepare($sql);
    $attributes  = $this->getAttributes(new \ReflectionClass($this));
    foreach ($attributes as $k => $v)
    {
      if (in_array($k, static::$_primary_keys))
        $query->bindValue(':' . $k, $v);
    }
    $query->execute();
  }
}