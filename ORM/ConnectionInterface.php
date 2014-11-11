<?php
/**
 * Created by PhpStorm.
 * User: joy
 * Email: joyruet06@gmail.com
 * Date: 2/4/14
 */
namespace Joy\ORM;

/**
 * Interface ConnectionInterface
 * Simple contract to provide database CRUD functionality
 */
interface ConnectionInterface{
    public function select($table, $where="", $bind="", $field="*");
    public function insert($table, $values);
    public function update($table, $values, $where, $bind="");
    public function delete($table, $where, $bind="");
}