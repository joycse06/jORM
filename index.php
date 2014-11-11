<?php
/**
 * Created by PhpStorm.
 * User: joy
 * Email: joyruet06@gmail.com
 * Date: 2/17/14
 */

require_once 'SplClassLoader.php';

$classLoader = $classLoader = new SplClassLoader('Joy', str_replace('\\','/',dirname(dirname(__FILE__))));
$classLoader->register();

use Joy\ORM\RedQModel as Model;
use Joy\ORM\MysqlDB as MysqlDriver;

$db_config = require_once __DIR__.DIRECTORY_SEPARATOR.'Config/database.php';

$conn = MysqlDriver::instance([
    'dsn' => $db_config['mysql']['dsn'],
    'user' => $db_config['mysql']['user'],
    'password' => $db_config['mysql']['password']
]);

Model::setConnection($conn);

/*
 * Lesson Model which extends the base ORM class
 */
class Lesson extends Model{}

//$lessons = Lesson::all();


$newLesson = new Lesson;

$newLesson->title = 'New one for Joy';
$newLesson->body = "New body from ORM at at ".date('H:i:s');

$newLesson->save();


$lastLesson = Lesson::find(1);
$lastLesson->title = "Title updated";
$lastLesson->save();

