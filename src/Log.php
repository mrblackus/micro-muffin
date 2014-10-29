<?php
/**
 * Created by JetBrains PhpStorm.
 * User: mathieu.savy
 * Date: 17/05/13
 * Time: 11:41
 * To change this template use File | Settings | File Templates.
 */

namespace MicroMuffin;

class Log
{
  private static $instance;
  private static $logDirectory;
  private $file;

  private static function getInstance()
  {
    if (self::$instance == null)
    {
      self::$instance = new Log();

      if (self::$instance->file)
      {
        fwrite(self::$instance->file, "\n====== ");
        fwrite(self::$instance->file, "Starting execution ");
        fwrite(self::$instance->file, "======\n");
        fwrite(self::$instance->file, "micro-muffin v" . LIB_VERSION_NUMBER . "\n");
      }
    }

    return self::$instance;
  }

  private function __construct()
  {
    $sFilePath = self::$logDirectory . "/real-time.log";

    if (is_writable(self::$logDirectory))
    {
      if (!file_exists($sFilePath) || is_writable($sFilePath))
        $this->file = fopen($sFilePath, "a");
      else
        $this->file = fopen('0-' . $sFilePath, 'a');
    }
  }

    /**
     * @param mixed $logDirectory
     */
    public static function setLogDirectory($logDirectory)
    {
        self::$logDirectory = $logDirectory;
    }

    public function __destruct()
  {
    if ($this->file)
      fclose($this->file);
  }

  /**
   * @param $string
   */
  public static function write($string)
  {
    $instance = self::getInstance();

    if ($instance->file)
    {
      $date = date("d/m H:i:s");
      fwrite($instance->file, "[" . $date . "]" . $string . "\n");
    }
  }

  /**
   * @param mixed $source
   * @return string
   */
  public static function dumpInVar($source)
  {
    ob_start();
    var_export($source);
    return ob_get_clean();
  }
}