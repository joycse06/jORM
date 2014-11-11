<?php
/**
 * Created by PhpStorm.
 * User: joy
 * Email: joyruet06@gmail.com
 * Date: 2/4/14
 */
namespace Joy\ORM;

use Joy\ORM\ConnectionInterface as ConnectionInterface;

/**
 * Class RedQModel
 * Will work as final Base Model
 */
abstract class RedQModel{

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected static $table;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected static $primaryKey = 'id';

    /**
     * The number of models to return for pagination.
     *
     * @var int
     */
    protected $perPage = 15;

    protected $timeStamps = true;

    /**
     * @var array
     * Will store all the attributes of the model
     */
    protected $attributes = array();


    /**
     * @var null
     */
    protected static $connection = null;

    /**
     * @var array
     */
    /**
     * @var array|bool
     */
    private
        $isNew = false,
        $modifiedFields = array();

    /**
     * Create a new RedQ model instance.
     *
     * @param  array  $attributes
     * @return void
     */
    final public function __construct(array $attributes = array(), $new = true)
    {
        $this->isNew = $new;
        $this->fill($attributes);
    }

    /**
     * Check if the instance is new one, Used in the save method
     * @return bool
     */
    public function isNew(){
        return $this->isNew;
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param  array  $attributes
     * @return \Illuminate\Database\Eloquent\Model|static
     */
    public function fill(array $attributes)
    {
        foreach ($attributes as $key => $value)
        {
           $this->setAttribute($key, $value);
        }

        return $this;
    }


    /**
     * Return the Mysql Connection
     * @return null
     */
    public static function getConnection(){
        return self::$connection;
    }

    /**
     * @param ConnectionInterface $connection
     */
    public static function setConnection(ConnectionInterface $connection){
        self::$connection = $connection;
    }

    /**
     * Get an attribute from the model.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        $inAttributes = array_key_exists($key, $this->attributes);

        if ($inAttributes)
        {
                return $this->attributes[$key];
        }else{
            return null;
        }
    }

    /**
     * Set a given attribute on the model.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function setAttribute($key, $value)
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Get all of the current attributes on the model.
     *
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Magic getter method
     * @param String $key
     * @return mixed
     */
    public function __get($key){
        return $this->getAttribute($key);
    }

    /**
     * Magic Setter Method
     */
    public function __set($key,$value){
        $this->setAttribute($key,$value);
    }

    /**
     * Handle dynamic static method calls into the method.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        $class = get_called_class();
        if(substr($method,0,6) == 'findBy'){
            // Prepend field name to the parameter List and call the proper method.
            $field = strtolower(preg_replace('/\B([A-Z])/','_${1}',substr($method,6)));
            array_unshift($parameters, $field);
            return call_user_func_array(array($class, 'findByField'), $parameters);
        }

        throw new \Exception(sprintf("There is no static method %s in the %s class",$method, $class));
    }

    /**
     * Retrieve record by a field
     * @param $field
     * @param $value
     */
    public static function findByField($field, $value){
        if (!is_string($field)){
            throw new \InvalidArgumentException("The field name must be a string.");
        }
        $operator = (strpos($value,"%") === false) ? '=': 'LIKE';

        $where = " $field $operator :$field";
        $bind = [":$field" => $value];

        return self::getConnection()->select(self::getTable(), $where, $bind);
    }

    /**
     * @param $primaryKey
     * @throws InvalidArgumentException
     */
    public static function find($primaryKey){
        if(!is_int($primaryKey)){
            throw new \InvalidArgumentException("Primary Key must be an Integer");
        }
        $where = " ".self::getPrimaryKey() . " = :" . self::getPrimaryKey();
        $bind[":" . self::getPrimaryKey()] = $primaryKey;

        $row = self::getConnection()->select(self::getTable(), $where, $bind);

        $reflectionObj = new \ReflectionClass(get_called_class());

        return $reflectionObj->newInstanceArgs(array($row[0], false));
    }

    public static function all(){
        $collection = array();

        $rows = self::getConnection()->select(self::getTable());
        $reflectionObject = new \ReflectionClass(get_called_class());
        foreach($rows as $row){
            $modelObj = $reflectionObject->newInstanceArgs(array($row,false));
            $collection[] = $modelObj;
        }
        return $collection;
    }

    public function save(){
        if($this->isNew()){
            $this->insert();
        }else{
            $this->update();
        }

    }

    private function insert(){
        // check and set the Timestamps if set to true
        if($this->timeStamps && is_null($this->created_at)){
            $this->created_at = date("Y-m-d H:i:s");
            $this->updated_at = date("Y-m-d H:i:s");
        }
        $insertID = self::getConnection()->insert(self::getTable(),$this->attributes);

        $this->{self::getPrimaryKey()} = $insertID;

        $this->isNew = false;
    }

    private function update(){
        $where = " ". self::getPrimaryKey() . "= :". self::getPrimaryKey();
        $bind[":" . self::getPrimaryKey()] = $this->attributes[self::getPrimaryKey()];

        $array = $this->attributes;
        unset($array[self::getPrimaryKey()]);

        return self::getConnection()->update(self::getTable(),$array, $where, $bind);

    }

    /**
     * Handle dynamic method calls into the Model.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        throw new \Exception(sprintf("No method named %s found on the class %s",$method,get_called_class()));
    }

    /**
     * Converts the model into an Array
     * @return Array
     */
    public function toArray(){
        return $this->attributes;
    }

    /**
     * Convert the model into an array and returns in JSON format
     */
    public function toJSON(){
        return json_encode($this->toArray());
    }

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public static function getTable()
    {
        if (isset(static::$table)) return static::$table;

        return str_replace('\\', '', self::snake_case(self::str_plural(self::class_basename(get_called_class()))));
    }

    /**
     * Set the table associated with the model.
     *
     * @param  string  $table
     * @return void
     */
    public function setTable($table)
    {
        $this->table = $table;
    }

    /**
     * Get the Primary Key of the Table
     * @return string
     */
    public static function getPrimaryKey(){
        if(isset(static::$primaryKey)) return static::$primaryKey;

        return self::$primaryKey;
    }


    /**
     * Convert a string to snake case.
     *
     * @param  string  $value
     * @param  string  $delimiter
     * @return string
     */
    public static function snake_case($value, $delimiter = '_')
    {
        $replace = '$1'.$delimiter.'$2';

        return ctype_lower($value) ? $value : strtolower(preg_replace('/(.)([A-Z])/', $replace, $value));
    }

    /**
     * Very simple barebone Pluralizer, probably the dumbest one, Replace later
     * @param $str
     * @return string
     */
    public static function str_plural($str){
        return $str.'s';
    }

    /**
     * Get the class "basename" of the given object / class.
     *
     * @param  string|object  $class
     * @return string
     */
    public static function class_basename($class)
    {
        $class = is_object($class) ? get_class($class) : $class;

        return basename(str_replace('\\', '/', $class));
    }


}