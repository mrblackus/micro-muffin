<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Mathieu
 * Date: 14/07/13
 * Time: 15:18
 * To change this template use File | Settings | File Templates.
 */

namespace MicroMuffin;

use MicroMuffin\Generator\Driver;
use MicroMuffin\Router\Route;
use MicroMuffin\Router\Router;
use MicroMuffin\Error;

class MicroMuffin
{
  const ENV_DEV  = 0;
  const ENV_PROD = 1;

  /** @var MicroMuffin */
  private static $instance = null;

  /** @var bool */
  private static $bInitialized = false;

  /** @var Route */
  private $route;

  /** @var Controller */
  private $controller;

  /** @var string */
  private $action;

  public static function init()
  {
    require_once(__DIR__ . '/config.php');

    ClassLoader::register();
  }

  private function route()
  {
    //Route determination
    $url = Tools::getParam("url", null);
    Log::write('MicroMuffin::route : url called is ' . $url);

    $route = Router::get(!is_null($url) ? $url : "");
    if (!is_null($route))
      $this->route = $route;
    else
    {
      header("HTTP/1.0 404 Not Found");
      $e = new Error("Page not found", "The page you are looking for doesn't exist.");
      $e->display();
    }
  }

  private function checkRoute()
  {
    $className = $this->route->getController() . 'Controller';
    if (class_exists($className))
    {
      $this->controller = new $className();
      if (method_exists($this->controller, $this->route->getAction()))
        $this->action = $this->route->getAction();
      else
      {
        //Undefined action
        $e = new Error('Undefined action', 'Action ' . $this->route->getAction() . ' doesn\'t exist on ' . $this->route->getController() . ' controller.');
        $e->display();
      }
    }
    else
    {
      //Undefined controller
      $e = new Error('Undefined controller', $this->route->getController() . ' doesn\'t exist.');
      $e->display();
    }
  }

  private function execute()
  {
    if (method_exists($this->controller, 'before_filter'))
      $this->controller->before_filter($this->route->getParameters());

    $action = $this->action;
    $this->controller->$action($this->route->getParameters());

    //View displaying
    $render = $this->controller->getRender();
    if ($render != "false")
    {
      Internationalization::init();
      $twig_options = array('cache' => false, 'autoescape' => false, 'strict_variables' => true);

      $loader = new \Twig_Loader_Filesystem('../' . VIEW_DIR . strtolower($this->route->getController()));
      $twig   = new \Twig_Environment($loader, $twig_options);
      $twig->addFilter("tr", new \Twig_Filter_Function("\\MicroMuffin\\Internationalization::translate"));
      $twig->addFilter("url", new \Twig_Filter_Function("\\MicroMuffin\\Tools::sanitizeForUrl"));

      $page = $twig->render(($render == "true" ? $this->action : $render) . ".html.twig", $this->controller->getVariables());

      //Base layout execution and displaying
      $layout = $this->controller->getRenderLayout();
      if ($layout != "false")
      {
        $loader = new \Twig_Loader_Filesystem('../' . VIEW_DIR . 'base');
        $twig   = new \Twig_Environment($loader, $twig_options);
        $twig->addFilter("tr", new \Twig_Filter_Function("\\MicroMuffin\\Internationalization::translate"));
        $twig->addFilter("url", new \Twig_Filter_Function("\\MicroMuffin\\Tools::sanitizeForUrl"));

        $base = new \BaseController();
        $base->$layout();
        $params          = $base->getVariables();
        $params          = array_merge($params, $this->controller->getLayoutVariables());
        $params['_page'] = $page;
        echo $twig->render($layout . ".html.twig", $params);
      }
      else
        echo $page;
    }
  }

  private function __construct()
  {
    $this->controller = null;
    $this->route      = null;
    $this->action     = null;
  }

  /**
   * @return Route
   */
  public function getRoute()
  {
    return $this->route;
  }

  /**
   * @throws \Exception
   * @return Driver
   */
  public static function getDBDriver()
  {
    if (!self::$bInitialized)
      self::init();

    if (DBDRIVER == Generator\DriverType::POSTGRESQL)
      $driver = new Generator\PostgreSqlDriver();
    else
      throw new \Exception('Invalid database driver');

    return $driver;
  }

  /**
   * @return MicroMuffin
   */
  public static function getInstance()
  {
    return self::$instance;
  }

  public static function run()
  {
    self::$instance = new MicroMuffin();
    self::init();
    self::$instance->route();
    self::$instance->checkRoute();
    self::$instance->execute();
  }
}