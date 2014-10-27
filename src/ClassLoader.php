<?php
/**
 * Created by PhpStorm.
 * User: mathieu
 * Date: 27/10/14
 * Time: 22:25
 */

namespace MicroMuffin;

class ClassLoader
{
    public static function register()
    {
        \Illuminate\Support\ClassLoader::register();
    }

    public static function addDirectories(Array $array)
    {
        \Illuminate\Support\ClassLoader::addDirectories($array);
    }
} 