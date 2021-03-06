<?php
/**
 * Created by JetBrains PhpStorm.
 * User: savy_m
 * Date: 24/05/13
 * Time: 15:54
 * To change this template use File | Settings | File Templates.
 */

namespace MicroMuffin;

class Error
{
  protected $title;
  protected $message;

  public function __construct($t, $m)
  {
    $this->title = $t;
    $this->message = $m;
  }

  public function display()
  {
    echo '<h1>Error : ' . $this->title . '</h1>';
    echo 'An error happened :</br >';
    echo $this->message;
    die();
  }
}