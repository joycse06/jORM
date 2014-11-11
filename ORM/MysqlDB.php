<?php
/**
 * Created by PhpStorm.
 * User: joy
 * Email: joyruet06@gmail.com
 * Date: 2/4/14
 */

namespace Joy\ORM;

use \PDO;
/**
 * Class MysqlDB
 */
class MysqlDB extends \PDO implements ConnectionInterface {

    /**
     * @var resource
     * Will be storehouse for the DB instance to work as a singleton
     */
    private static $instance;

    /**
     * @var string
     */
    private $error;
    /**
     * @var string
     */
    private $sql;
    /**
     * @var string
     */
    private $bind;
    /**
     * @var
     */
    private $model;

    /**
     * Sets the model associated with this
     * @param mixed $model
     */
    public function setModel($model)
    {
        $this->model = $model;
    }

    /**
     * Gets the model with this
     * @return mixed
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * @param $config
     * Singleton method which will return an instance of this class
     */
    public static function instance(Array $config = array()){
        if( !isset( self::$instance )){
            self::$instance = new self($config['dsn'],$config['user'], $config['password']);
        }
        return self::$instance;
    }

    /**
     * @param $dsn
     * @param string $user
     * @param string $passwd
     */
    public function __construct($dsn, $user="", $passwd="") {
        $options = array(
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        );

        try {
            parent::__construct($dsn, $user, $passwd, $options);
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
        }
    }

    /**
     * Filter Table Attributes using column names from database
     * @param $table
     * @param $info
     * @return array
     */
    private function filterTableFields($table, $info) {
        $driver = $this->getAttribute(PDO::ATTR_DRIVER_NAME);
        if($driver == 'sqlite') {
            $sql = "PRAGMA table_info('" . $table . "');";
            $key = "name";
        }
        elseif($driver == 'mysql') {
            $sql = "DESCRIBE " . $table . ";";
            $key = "Field";
        }
        else {
            $sql = "SELECT column_name FROM information_schema.columns WHERE table_name = '" . $table . "';";
            $key = "column_name";
        }

        if(false !== ($list = $this->run($sql))) {
            $fields = array();
            foreach($list as $record)
                $fields[] = $record[$key];
            return array_values(array_intersect($fields, array_keys($info)));
        }
        return array();
    }

    /**
     * Clean up bindings
     * @param $bind
     * @return array
     */
    private function cleanup($bind) {
        if(!is_array($bind)) {
            if(!empty($bind))
                $bind = array($bind);
            else
                $bind = array();
        }
        return $bind;
    }

    /**
     * Generic Insert method to insert data using PDO
     * @param $table
     * @param $values
     * $return mixed
     */
    public function insert($table, $values)
    {
        // Remove items which are not present in the table
        $fields = $this->filterTableFields($table,$values);

        $sql = "INSERT INTO ".$table." ( ".implode($fields,', '). " ) VALUES(:".implode($fields,", :").")";
        $bind = array();

        // Prepare the Bind Array
        foreach($fields as $field){
            if($field == 'created_at' || $field == 'updated_at')
                $bind[":$field"] = date("Y-m-d H:i:s");
            else
                $bind[":$field"] = $values[$field];
        }
        return $this->run($sql, $bind);

    }

    /**
     * Generic Select method which will be used by the base model
     * @param $table
     * @param string $where
     * @param string $bind
     * @param string $fields
     * @return array|bool|int
     */
    public function select($table, $where="", $bind="", $fields="*")
    {
        $sql = "SELECT ".$fields." FROM ".$table;

        if(!empty($where))
            $sql .= " WHERE ".$where;
        $sql .= ";";
        return $this->run($sql, $bind);
    }


    /**
     * Update method to update data on Table
     * @param $table
     * @param $values
     * @param $where
     * @param string $bind
     * @return array|bool|int
     */
    public function update($table, $values, $where, $bind="")
    {
        $fields = $this->filterTableFields($table, $values);
        $fieldCount = sizeof($fields);

        $sql = "UPDATE ".$table." SET ";
        for($fieldIterator = 0; $fieldIterator < $fieldCount; $fieldIterator++){
            if($fieldIterator > 0)
                $sql .= ", ";
            $sql .= $fields[$fieldIterator] . " = :update_".$fields[$fieldIterator];
        }

        $sql .= " WHERE " . $where . ";";
        // Now build the Bind array

        $bind = $this->cleanup($bind);
        foreach($fields as $field){
            if($field == 'updated_at')
                $bind[":update_$field"] = date("Y-m-d H:i:s");
            else
                $bind[":update_$field"] = $values[$field];
        }
        return $this->run($sql, $bind);

    }


    /**
     * Delete method to delete Record based on Conditions
     * @param $table
     * @param $where
     * @param string $bind
     * @return array|bool|int
     */
    public function delete($table, $where, $bind ="")
    {
        $sql = "DELETE FROM " . $table . " WHERE ". $where . ";";

        return $this->run($sql, $bind);
    }


    /**
     * This function actually runs the Query using PDO
     * @param $query
     * @param string $bind
     * @return array|bool|int
     */
    public function run($query, $bind = ""){
        var_dump(array($query,$bind));
        $this->sql = trim($query);
        $this->bind = $this->cleanup($bind);
        $this->error = "";

        try{
            $pdoStatement = $this->prepare($query);
            if($pdoStatement->execute($this->bind) != false){
                if(preg_match("/^(" . implode("|", array('select', 'pragma', 'describe')) . ")/i", $this->sql))
                    return $pdoStatement->fetchAll(PDO::FETCH_ASSOC);
                elseif(preg_match("/^(" . implode('|', array( 'delete', 'update')) . ")/i", $this->sql))
                    return $pdoStatement->rowCount();
                elseif(preg_match("/^insert/i", $this->sql))
                    return $this->lastInsertId();

            }
        }catch(PDOException $error){
            $this->error = $error->getMessage();
            // TODO: Include any more sophisticated Error handling later
            return false;
        }
    }


}

