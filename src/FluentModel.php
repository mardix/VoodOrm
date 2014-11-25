<?php
/**
 * -----------------------------------------------------------------------------
 * FluentModel
 * -----------------------------------------------------------------------------
 * @author      Mardix (http://twitter.com/mardix)
 * @modified by Terry Cullen (http://terah.com.au)
 * @github      https://github.com/terah/
 * @package     VoodooPHP (https://github.com/mardix/Voodoo/)
 *
 * @copyright   (c) 2014 Mardix (http://github.com/mardix)
 * @license     MIT
 * -----------------------------------------------------------------------------
 *
 * About FluentModel
 *
 * FluentModel is a fluent interface to pdo which functions as both a fluent select query API and a CRUD model class.
 * FluentModel is built on top of PDO and is well fit for small to mid-sized projects, where the emphasis
 * is on simplicity and rapid development rather than infinite flexibility and features.
 *
 * Learn more: https://github.com/mardix/VoodOrm
 *
 */

namespace Terah\FluentModel;


use Closure;
use PDO;
use DateTime;
use Utilities;
use vakata\database\Exception;

class FluentModel
{
    const NAME                  = 'FluentModel';
    const VERSION               = '0.1';

    // RELATIONSHIP CONSTANT
    const HAS_ONE               =  1;       // OneToOne. Eager Load data
    const HAS_MANY              =  2;      // OneToMany. Eager load data

    const OPERATOR_AND          = ' AND ';
    const OPERATOR_OR           = ' OR ';
    const ORDERBY_ASC           = 'ASC';
    const ORDERBY_DESC          = 'DESC';
    const SAVE_INSERT           = 'INSERT';
    const SAVE_UPDATE           = 'UPDATE';

    /* @var \PDO $_pdo */
    protected $_pdo                 = null;
    /* @var \PDOStatement $_pdo_stmt*/
    protected $_pdo_stmt              = null;
    protected $_table_token           = '';
    protected $_select_fields         = [];
    protected $_join_sources          = [];
    protected $_limit                 = null;
    protected $_offset                = null;
    protected $_order_by              = [];
    protected $_group_by              = [];
    protected $_where_parameters      = [];
    protected $_where_conditions      = [];
    protected $_and_or_operator       = self::OPERATOR_AND;
    protected $_having                = [];
    protected $_wrap_open             = false;
    protected $_last_wrap_position    = 0;
    protected $_is_fluent_query       = true;
    protected $_pdo_executed          = false;
    protected $_data                  = [];
    protected $_debug_sql_query       = false;

    protected $_sql_query             = '';
    protected $_sql_parameters        = [];
    protected $_dirty_fields          = [];
    protected $_reference_keys        = [];
    protected static $_references     = [];
    protected $_distinct            = false;
    protected $_errors              = [];

    protected $_table_alias         = '';
    protected $_is_single           = false;
    protected $_connection          = null;
    protected $_table_name          = null;
    protected $_display_column      = null;
    protected $_auto_joins          = [];
    protected $_rules               = [];

    protected $_read_only           = false;
    protected $_verbose             = false;
    protected $_allow_meta_override = false;
    protected $_filter_meta         = null;
    protected $_requested_fields    = null;
    protected $_log_queries         = false;

    // Table structure
    public $table_structure         = [
        'primaryKeyname'    => 'id',
        'foreignKeyname'    => '%s_id'
    ];

    /**
     * @var array
     */
    protected static $_pdo_conns        = null;
    protected static $_model_namespace  = null;
    /**
     * @param array $config
     *
     * @throws \Exception
     */
    static public function init(array $config, $model_namespace=null)
    {
        foreach ( $config as $connection => $connection_config )
        {
            \Assert\that($connection_config)->keysExist(['host', 'host', 'port', 'name', 'user', 'driver', 'log_queries']);
            static::addConnection($connection, static::getPdoCallableFromConf($connection_config), $connection_config);
        }
        static::$_model_namespace = $model_namespace;
    }


    /**
     * Load a model via it's static interface
     *
     * @param array $settings
     *
     * @return FluentModel
     */
    public static function load(array $settings=[])
    {
        $called_class_name   = get_called_class();
        return new $called_class_name($settings);
    }

    /**
     * Load a model by it's table name
     *
     * @param $table_name
     *
     * @return FluentModel
     * @throws \Exception
     */
    public static function loadModel($table_name)
    {
        $model_name = static::getFullModelName($table_name);
        $model_obj  = new $model_name;
        if ( !( $model_obj instanceof FluentModel ) )
        {
            throw new \Exception();
        }
        return $model_obj;
    }

    /**
     * @param $table_name
     *
     * @return string
     */
    public static function getFullModelName($table_name)
    {
        return static::$_model_namespace . Utilities\Inflector::classify($table_name);
    }

    /**
     * Add a pdo connection to the pool
     *
     * @param          $name
     * @param Closure  $pdo_function
     * @param array    $connection_config
     */
    static public function addConnection($name, Closure $pdo_function, array $connection_config)
    {
        \Assert\that($name)->string()->notEmpty();

        static::$_pdo_conns[$name] = [
            'instance'      => null,
            'loader'        => $pdo_function,
            'config'        => $connection_config,
        ];
    }

    /**
     * @param $name
     *
     * @return \Pdo
     */
    static public function getPdoConnection($name, $parameter='instance')
    {
        \Assert\that($name)->string()->notEmpty();
        \Assert\that(static::$_pdo_conns)->keyExists($name);
        if ( ( is_null($parameter) || $parameter === 'instance' ) && is_null(static::$_pdo_conns[$name]['instance']) )
        {
            \Assert\that(static::$_pdo_conns[$name])->keyExists('loader');
            $loader = static::$_pdo_conns[$name]['loader'];
            static::$_pdo_conns[$name]['instance'] = $loader();
        }
        return is_null($parameter) ? static::$_pdo_conns[$name] : static::$_pdo_conns[$name][$parameter];
    }

    static public function getConnectionConfig($name)
    {
        return static::getPdoConnection($name, 'config');
    }

    /**
     * Create a pdo instance from config array
     *
     * @param array $connection_config
     *
     * @return null|Closure
     */
    static protected function getPdoCallableFromConf(array $connection_config)
    {
        \Assert\that($connection_config)->keysExist(['host', 'port', 'name', 'pass', 'user', 'driver', 'log_queries']);
        \Assert\that($connection_config['driver'])->inArray(['PDOMYSQL', 'PDOOCI'], "Invalid database driver specified");

        $host = $port = $name = $pass = $user = $driver = '';
        extract($connection_config);

        switch ($driver)
        {
            case 'PDOMYSQL':

                return function() use ($host, $port, $name, $user, $pass) {

                    $pdo = new \PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8", $user, $pass);
                    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                    $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
                    $pdo->exec("SET NAMES utf8 COLLATE utf8_unicode_ci");
                    return $pdo;
                };


            case 'PDOOCI':

                return function() use ($host, $port, $name, $user, $pass) {

                    $pdo = new \PDOOCI\PDO("{$name};charset=utf8", $user, $pass);
                    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                    return $pdo;
                };

        }
        return null;
    }

    /**
     * @param $conn_ident
     * @param $sql
     * @param $params
     *
     * @return \PDOStatement
     */
    static public function fetchStmt($conn_ident, $sql, $params)
    {
        $pdo_conn   = static::getPdoConnection($conn_ident);
        $stmt = $pdo_conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    static public function exec($conn_ident, $sql)
    {
        return static::getPdoConnection($conn_ident)->exec($sql);
    }

    /**
     * @param     $conn_ident
     * @param     $sql
     * @param     $params
     * @param int $fetchType
     *
     * @return \stdClass[]
     */
    static public function fetchAll($conn_ident, $sql, $params, $fetchType=\PDO::FETCH_OBJ)
    {
        return static::fetchStmt($conn_ident, $sql, $params)->fetchAll($fetchType);
    }

    /**
     * @param      $conn_ident
     * @param null $table
     *
     * @return array|mixed|null
     * @throws \Exception
     */
    public static function getTableColumnMeta($conn_ident, $table=null)
    {
        $pdo_conn           = static::getPdoConnection($conn_ident);
        $connection_config  = static::getConnectionConfig($conn_ident);
        \Assert\that($connection_config)->keysExist(['host', 'port', 'name', 'pass', 'user', 'driver', 'log_queries']);
        \Assert\that($connection_config['driver'])->inArray(['PDOMYSQL', 'PDOOCI'], "Invalid database driver specified");
        $table_meta         = [];
        switch ( $connection_config['driver'] )
        {
            case 'PDOMYSQL':

                $pdo_conn->exec('FLUSH TABLES;');
                $sql = <<<SQL
                    SELECT
                      c.TABLE_NAME                as table_name,
                      c.COLUMN_NAME               as column_name,
                      c.IS_NULLABLE               as is_nullable,
                      c.DATA_TYPE                 as data_type,
                      c.CHARACTER_MAXIMUM_LENGTH  as character_maximum_length,
                      c.NUMERIC_PRECISION         as numeric_precision,
                      c.COLUMN_TYPE               as column_type
                    FROM information_schema.columns c
                    LEFT JOIN information_schema.tables t ON c.TABLE_NAME = t.TABLE_NAME AND c.table_schema = t.table_schema
                    WHERE t.table_schema = :database
                    AND t.TABLE_TYPE = 'BASE TABLE';
SQL;
                $stmt = self::fetchStmt($conn_ident, $sql, ['database' => $connection_config['name']]);
                while ( $column = $stmt->fetchObject() )
                {
                    $table_meta[$column->table_name][$column->column_name] = $column;
                }
                ksort($table_meta);
                if ( !is_null($table) )
                {
                    return !empty($table_meta[$table]) ? [$table => $table_meta[$table]] : null;
                }
                return $table_meta;

            default:

                throw new \Exception('Not implemented');
        }
    }

    /**
     * @param      $conn_ident
     * @param null $table
     *
     * @returns array
     * @throws \Exception
     */
    public static function getForeignKeyMeta($conn_ident, $table=null)
    {
        $pdo_conn           = static::getPdoConnection($conn_ident);
        $connection_config  = static::getConnectionConfig($conn_ident);
        \Assert\that($connection_config)->keysExist(['host', 'port', 'name', 'pass', 'user', 'driver', 'log_queries']);
        \Assert\that($connection_config['driver'])->inArray(['PDOMYSQL', 'PDOOCI'], "Invalid database driver specified");
        $key_meta          = [];
        switch ( $connection_config['driver'] )
        {
            case 'PDOMYSQL':

                $pdo_conn->exec('FLUSH TABLES;');
                $sql = <<<SQL
                    SELECT
                        i.TABLE_NAME              as table_name,
                        i.CONSTRAINT_NAME         as constraint_name,
                        k.REFERENCED_TABLE_NAME   as referenced_table_name,
                        k.REFERENCED_COLUMN_NAME  as referenced_column_name,
                        k.COLUMN_NAME             as column_name
                    FROM information_schema.TABLE_CONSTRAINTS i
                    LEFT JOIN information_schema.KEY_COLUMN_USAGE k ON i.CONSTRAINT_NAME = k.CONSTRAINT_NAME
                    WHERE i.TABLE_SCHEMA = :database
                    AND i.CONSTRAINT_TYPE = 'FOREIGN KEY';
SQL;
                while ( $key = self::fetchStmt($conn_ident, $sql, ['database' => $connection_config['name']])->fetchObject() )
                {
                    $key_meta['foreign_keys'][$key->table_name][] = $key;
                }
                ksort($key_meta);
                if ( !is_null($table) )
                {
                    return !empty($key_meta[$table]) ? [$table => $key_meta[$table]] : null;
                }
                return $key_meta;

            default:

                throw new \Exception('Not implemented');
        }
    }

    public static function getAllTableMeta($conn_ident, $table=null)
    {
        return [
            'columns'       => static::getTableColumnMeta($conn_ident, $table),
            'foreign_keys'  => static::getForeignKeyMeta($conn_ident, $table),
        ];
    }



    /**
     * @param array $settings
     *
     * @throws \Exception
     */
    public function __construct(array $settings=[])
    {
        $connection_ident   = !empty($settings['connection']) ? $settings['connection'] : $this->_connection;
        $connection_details = static::getPdoConnection($connection_ident, null);
        $settings           = array_merge($settings, $connection_details['config']);
        $this->_read_only   = !empty($settings['dry_run']) ? boolval($settings['dry_run']) : false;
        $this->_verbose     = !empty($settings['verbose']) ? boolval($settings['verbose']) : false;
        $this->_log_queries = !empty($settings['log_queries']) ? $settings['log_queries'] : false;
        $primaryKeyName     = !empty($settings['private_key']) ? $settings['private_key'] : 'id';
        $foreignKeyName     = !empty($settings['foreign_key']) ? $settings['foreign_key'] : '%s_id';

        $this->_pdo          = $connection_details['instance'];
        $this->setStructure($primaryKeyName, $foreignKeyName);
    }

    public function beginTrans()
    {
        $this->_pdo->beginTransaction();
        return $this;
    }

    public function commitTrans()
    {
        $this->_pdo->commit();
        return $this;
    }

    public function rollbackTrans()
    {
        $this->_pdo->rollBack();
        return $this;
    }

    public function getCachedField($sField, $aConditions, $sCacheConfig='lookup_values')
    {
        $sCacheKey = md5(json_encode([
            'table'     => $this->_table_name,
            'field'     => $sField,
            'where'     => $aConditions,
            'type'      => __FUNCTION__,
        ]));
        return Utilities\Cache\Cache::remember($sCacheKey, function() use ($sField, $aConditions) {

            return $this->where($aConditions)->toField($sField);

        }, $sCacheConfig);
    }

    public function getCachedObject($aFields, $aConditions, $sCacheConfig='lookup_values')
    {
        $sCacheKey = md5(json_encode([
            'table'     => $this->_table_name,
            'fields'    => $aFields,
            'where'     => $aConditions,
            'type'      => __FUNCTION__,
        ]));
        return Utilities\Cache\Cache::remember($sCacheKey, function() use ($aFields, $aConditions) {

            return $this->select($aFields)->where($aConditions)->toObject();

        }, $sCacheConfig);
    }

    public function getCachedObjects($aFields, $aConditions, $sCacheConfig='lookup_values')
    {
        $sCacheKey = md5(json_encode([
            'table'     => $this->_table_name,
            'fields'    => $aFields,
            'where'     => $aConditions,
            'type'      => __FUNCTION__,
        ]));
        return Utilities\Cache\Cache::remember($sCacheKey, function() use ($aFields, $aConditions) {

            return $this->select($aFields)->where($aConditions)->toObjects();

        }, $sCacheConfig);
    }

    public function getCachedList($sKeyedOn, $sShowVal, $aConditions, $sCacheConfig='lookup_values')
    {
        $sCacheKey = md5(json_encode([
            'table'     => $this->_table_name,
            'fields'    => [$sKeyedOn, $sShowVal],
            'where'     => $aConditions,
            'type'      => __FUNCTION__,
        ]));
        return Utilities\Cache\Cache::remember($sCacheKey, function() use ($sKeyedOn, $sShowVal, $aConditions) {

            return $this->select([$sKeyedOn, $sShowVal])->where($aConditions)->toList($sKeyedOn, $sShowVal);

        }, $sCacheConfig);
    }

    /**
     * Define the working table and create a new instance
     *
     * @param  string   $tableName - Table name
     * @param  string   $alias     - The table alias name
     * @return FluentModel
     */
    public function table($tableName, $alias = '')
    {
        $instance = clone($this);
        $instance->_table_name = $tableName;
        $instance->_table_token = $tableName;
        $instance->setTableAlias($alias);
        $instance->reset();
        return $instance;
    }

    /**
     * Return the name of the table
     * @return string
     */
    public function getTablename()
    {
        return $this->_table_name;
    }

    public function displayColumn()
    {
        return $this->_display_column;
    }

    public function errors()
    {
        return $this->_errors;
    }

    public function getValidationRules()
    {
        return $this->_rules;
    }

    public function validate(array $record, $sType='insert')
    {
        $this->_errors = [];
        foreach ( $this->getValidationRules() as $column => $rules )
        {
            if ( !isset($record[$column]) )
            {
                // @todo: What to do about missing columns?
                continue;
            }
            foreach ( $rules as $rule_name => $validator )
            {
                if ( $sType === 'insert' && $column === 'id' && $rule_name === 'not_empty' )
                {
                    continue;
                }
                $validator = is_callable($validator) ? $validator : function($field_val) use ($validator) {

                    return !preg_match($validator, $field_val);
                };
                if ( ! $validator($record[$column]) )
                {
                    $this->_errors[$column][] = $rule_name;
                }
            }
        }
        return empty($this->_errors) ? true : false;
    }

    public function allowMetaColumnOverride($allow=null)
    {
        $this->_allow_meta_override = $allow;
        return $this;
    }

    /**
     * Set the table alias
     *
     * @param string $alias
     * @return FluentModel
     */
    public function setTableAlias($alias)
    {
        $this->_table_alias = $alias;
        return $this;
    }

    public function getTableAlias()
    {
        return $this->_table_alias;
    }

    /**
     *
     * @param string $primaryKeyName - the primary key, ie: id
     * @param string $foreignKeyName - the foreign key as a pattern: %s_id,
     *                                  where %s will be substituted with the table name
     * @return FluentModel
     */
    public function setStructure($primaryKeyName = 'id', $foreignKeyName = '%s_id')
    {
        $this->table_structure = [
            'primaryKeyname' => $primaryKeyName,
            'foreignKeyname' => $foreignKeyName
        ];
        return $this;
    }

    public function arrayToList($aList, $bQuote=true)
    {
        if ( $bQuote )
        {
            array_map(function($mElem){
                return "'{$mElem}'";
            }, $aList);
        }
        return implode(',', $aList);
    }

    /**
     * Return the table structure
     * @return Array
     */
    public function getStructure()
    {
        return $this->table_structure;
    }

    /**
     * Get the primary key name
     * @return string
     */
    public function getPrimaryKeyname()
    {
        return $this->formatKeyname($this->table_structure['primaryKeyname'], $this->_table_name);
    }

    /**
     * Get foreign key name
     * @return string
     */
    public function getForeignKeyname()
    {
        return $this->formatKeyname($this->table_structure['foreignKeyname'], $this->_table_name);
    }

    /**
     * Return if the entry is of a single row
     *
     * @return bool
     */
    public function isSingleRow()
    {
        return $this->_is_single;
    }

/*******************************************************************************/


    /**
     * To execute a raw query
     *
     * @param string $query
     * @param array $parameters
     * @param bool  $return_as_pdo_stmt
     * @param Closure $callback_fn
     *
     * @return $this|int|\PDOStatement
     * @throws \Exception
     */
    public function query($query, array $parameters=[], $return_as_pdo_stmt=false, Closure $callback_fn=null)
    {
        $this->_sql_parameters = $parameters;
        $this->_sql_query = $query;

        if ($this->_debug_sql_query)
        {
            return $this;
        }
        $sBuiltQuery = '';
        if ( $this->_log_queries )
        {
            $sBuiltQuery = $this->buildQuery($query, $parameters);
            Utilities\Logger::db_all($sBuiltQuery);
        }
        try{
            $secs_taken             = microtime(true);
            $this->_pdo_stmt = $this->_pdo->prepare($query);
            $this->_pdo_executed = $this->_pdo_stmt->execute($parameters);
            $secs_taken             = microtime(true)  - $secs_taken;
            if ( $this->_log_queries && $secs_taken > 5 )
            {
                $secs_taken = Utilities\StringUtils::secondsToWords(microtime(true)  - $secs_taken);
                Utilities\Logger::db_all("SLOW QUERY - {$secs_taken}:\n{$sBuiltQuery}");
            }
        }
        catch(\Exception $e)
        {
            Utilities\Logger::db_all("FAILED: " . $this->buildQuery($query, $parameters) . "\n WITH ERROR:\n" . $e->getMessage());
            throw $e;
        }
        if ( is_callable($callback_fn) )
        {
            $success_cnt   = 0;
            $this->_pdo_stmt->setFetchMode(\PDO::FETCH_ASSOC);
            while ( $oData = static::_fetchAndFormat($this->_pdo_stmt) )
            {
                if ( $callback_fn($oData) )
                {
                    $success_cnt++;
                }
            }
            return $success_cnt;
        }
        if ( $return_as_pdo_stmt )
        {
            return $this->_pdo_stmt;
        }
        $this->_is_fluent_query = true;
        return $this;
    }

    public function buildQuery($sql, array $params=[])
    {
        $indexed = $params == array_values($params);
        foreach ( $params as $k => $v )
        {
            if ( is_string($v) )
            {
                $v = "'{$v}'";
            }
            if ( $indexed )
            {
                $sql = preg_replace('/\?/', $v, $sql, 1);
            }
            else
            {
                $sql = str_replace(":$k", $v, $sql);
                $sql = str_replace("$k", $v, $sql);
            }
        }
        return $sql;
    }

    /**
     * Execute a sql query
     *
     * @param $sql
     *
     * @return int
     */
    public function execute($sql) {

        return $this->_pdo->exec($sql);
    }

    /**
     * @param     \PDOStatement $pdo_stmt
     * @param bool $as_array
     *
     * @return array|bool|object
     */
    static public function _fetchAndFormat(\PDOStatement $pdo_stmt, $as_array=false)
    {
        $data = $pdo_stmt->fetch();
        if ( ! $data )
        {
            return false;
        }
        $data = Utilities\ArrayUtils::trim(array_change_key_case($data, CASE_LOWER));
        return $as_array ? $data : (object)$data;
    }

    /**
     * Return the number of affected row by the last statement
     *
     * @return int
     */
    public function rowCount()
    {
        return ($this->_pdo_executed == true) ? $this->_pdo_stmt->rowCount() : 0;
    }

    /**
     * To find all rows and create their instances
     * Use the query builder to build the where clause or $this->query with select
     * If a callback function is provided, the 1st arg must accept the rows results
     *
     * $this->find(function($rows){
     *   // do more stuff here...
     * });
     *
     * @param Closure $callback - run a function on the returned rows
     * @param bool     $bAsStmt
     * @param bool     $bAddSuccessCount
     *
     * @return array|\PDOStatement
     * @throws \Exception
     */
    public function find(Closure $callback = null, $as_pdo_stmt=false, $tally_success_cnt=false)
    {
        if ( $this->_is_fluent_query && $this->_pdo_stmt == null )
        {
            $this->query($this->getSelectQuery(), $this->getWhereParameters());
        }
        //Debug SQL Query
        if ($this->_debug_sql_query)
        {
            $this->debugSqlQuery(false);
            return false;
        }
        if ( $this->_pdo_executed != true )
        {
            return false;
        }
        if ( $tally_success_cnt && is_callable($callback) )
        {
            $success_cnt   = 0;
            $this->_pdo_stmt->setFetchMode(\PDO::FETCH_ASSOC);
            while ( $oData = static::_fetchAndFormat($this->_pdo_stmt) )
            {
                if ( $callback($oData) )
                {
                    $success_cnt++;
                }
            }
            return $success_cnt;
        }
        if ( $as_pdo_stmt )
        {
            if ( is_callable($callback) )
            {
                return $callback($this->_pdo_stmt);
            }
            return $this->_pdo_stmt;
        }
        $allRows = $this->_pdo_stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->reset();

        if ( is_callable($callback) )
        {
            return $callback($allRows);
        }

        if( !count($allRows) )
        {
            return [];
        }

//        // Holding all foreign keys matching the structure
//        $matchForeignKey = function($key)
//        {
//            return preg_match("/".str_replace("%s","[a-z]", $this->table_structure["foreignKeyname"])."/i", $key);
//        };
//        foreach ( $allRows as $index => &$row )
//        {
//            if ( $index == 0 )
//            {
//                $this->_reference_keys = [$this->table_structure["primaryKeyname"] => []];
//                foreach( array_keys($row) as $_rowK )
//                {
//                    if ( $matchForeignKey($_rowK) )
//                    {
//                        $this->_reference_keys[$_rowK] = [];
//                    }
//                }
//            }
//            foreach( $row as $rowK => &$rowV )
//            {
//                if( array_key_exists($rowK, $this->_reference_keys) )
//                {
//                    $this->_reference_keys[$rowK][] = $rowV;
//                    $this->_reference_keys[$rowK] = array_unique($this->_reference_keys[$rowK]);
//                }
//            }
//        }
//        unset($row);
        $rowsObject = [];
        foreach ($allRows as $row)
        {
            $rowsObject[] = $this->fromArray($row);
        }
        return $rowsObject;
    }

    /**
     * @param string $sKeyedOn
     * @param string $sShowField
     *
     * @return array
     */
    public function toList($sKeyedOn=null, $sShowField=null)
    {
        return $this->find(function(\PDOStatement $oStmt) use ($sKeyedOn, $sShowField) {

            $aResult = [];
            $oStmt->setFetchMode(\PDO::FETCH_ASSOC);
            while ( $aData = static::_fetchAndFormat($oStmt, true) )
            {
                if ( is_null($sKeyedOn) )
                {
                    $aKeys      = array_keys($aData);
                    $sKeyedOn   = $aKeys[0];
                    $sShowField = isset($aKeys[1]) ? $aKeys[1] : null;
                }
                $aResult[$aData[$sKeyedOn]] = !is_null($sShowField) ? $aData[$sShowField] : $aData;
            }
            return $aResult;

        }, true);
    }

    public function toAssoc($sKeyedOn=null)
    {
        return $this->toResults(true, $sKeyedOn);
    }

    public function toObjects($sKeyedOn=null)
    {
        return $this->toResults(false, $sKeyedOn);
    }

    public function toResults($bAsAssoc=true, $sKeyedOn=null)
    {
        return $this->find(function(\PDOStatement $oStmt) use ($bAsAssoc, $sKeyedOn) {

            $aResult = [];
            $oStmt->setFetchMode(\PDO::FETCH_ASSOC);
            while ( $aData = static::_fetchAndFormat($oStmt, true) )
            {
                $sKeyedOn = is_null($sKeyedOn) ? 'id' : $sKeyedOn;
                $sKeyedOn = isset($aData[$sKeyedOn]) ? $sKeyedOn : null;
                if ( is_null($sKeyedOn) )
                {
                    $aKeys      = array_keys($aData);
                    $sKeyedOn   = $aKeys[0];
                }
                $aResult[$aData[$sKeyedOn]] = $bAsAssoc ? $aData : (object)$aData;
            }
            return $aResult;

        }, true);
    }

    public function toField($sField='id', $iItemId=null)
    {
        $aResult = $this->findOne($iItemId);
        if ( !$aResult )
        {
            return null;
        }
        $aResult = $aResult->toArray();
        return isset($aResult[$sField]) ? $aResult[$sField] : null;
    }
    /**
     * Return one row
     *
     * @param  int      $id - use to fetch by primary key
     * @return FluentModel
     */
    public function findOne($id = null)
    {
        if ( $id )
        {
            $this->wherePK($id, true);
        }
        $this->limit(1);
        // Debug the SQL Query
        if ( $this->_debug_sql_query )
        {
            $this->find();
            return false;
        }
        $findAll = $this->find();
        return $findAll ? array_shift($findAll) : false;
    }

    /**
     * Create an instance from the given row (an associative
     * array of data fetched from the database)
     *
     * @param array $data
     *
     * @return FluentModel
     */
    public function fromArray(Array $data)
    {
        $row  = clone($this);
        $row->reset();
        $row->_is_single = true;
        $row->_data = $data;
        return $row;
    }

/*------------------------------------------------------------------------------
                                Fluent Query Builder
*-----------------------------------------------------------------------------*/

    /**
     * Create the select clause
     *
     * @param  mixed    $columns  - the column to select. Can be string or array of fields
     * @param  string   $alias - an alias to the column
     * @return FluentModel
     */
    public function select($columns = '*', $alias = null)
    {
        $this->_is_fluent_query = true;

        if ( $alias && !is_array($columns) )
        {
            $columns .= " AS {$alias} ";
        }
        if ( $columns === '*' && !empty($this->_columns) )
        {
            $columns = array_keys($this->_columns);
        }
        if( is_array($columns) )
        {
            $this->_select_fields = array_merge($this->_select_fields, $columns);
        }
        else
        {
            $this->_select_fields[] = $columns;
        }
        return $this;
    }

    public function distinct($bDistinct=true)
    {
        $this->_distinct = $bDistinct;
        return $this;
    }

    public function addSelect($columns)
    {
        if ( empty($this->_select_fields) )
        {
            $this->select('*');
        }
        if ( is_array($columns) )
        {
            $this->_select_fields = array_merge($this->_select_fields, $columns);
        }
        else
        {
            $this->_select_fields[] = $columns;
        }
    }

    public function withBelongsTo($withBelongsTo=true)
    {
        if ( $withBelongsTo && !empty($this->_auto_joins['belongsTo']) )
        {
            foreach ( $this->_auto_joins['belongsTo'] as $sAlias => $aConfig )
            {
                list($sTable, $sJoinCol, $sField) = $aConfig;
                $sCondition = "{$sAlias}.id = {$this->_table_alias}.{$sJoinCol}";
                $this->leftJoin($sTable, $sCondition, $sAlias);
                $this->addSelect($sField);
            }
        }
        return $this;
    }

    /**
     * Add where condition, more calls appends with AND
     *
     * @param string $condition possibly containing ? or :name
     * @param mixed $parameters accepted by PDOStatement::execute or a scalar value
     * @param mixed ...
     * @return FluentModel
     */
    public function where($condition, $parameters = [])
    {
        $this->_is_fluent_query = true;

        // By default the and_or_operator and wrap operator is AND,
        if ( $this->_wrap_open || ! $this->_and_or_operator )
        {
            $this->_and();
        }

        // where(array("column1" => 1, "column2 > ?" => 2))
        if ( is_array($condition) )
        {
            foreach ($condition as $key => $val)
            {
                $this->where($key, $val);
            }
            return $this;
        }

        $args = func_num_args();
        if ( $args != 2 || strpbrk($condition, '?:') )
        { // where('column < ? OR column > ?', array(1, 2))
            if ( $args != 2 || !is_array($parameters) )
            { // where('column < ? OR column > ?', 1, 2)
                $parameters = func_get_args();
                array_shift($parameters);
            }
        }
        else if ( !is_array($parameters) )
        {//where(column,value) => column=value
            $condition .= ' = ?';
            $parameters = [$parameters];
        }
        else if ( is_array($parameters) )
        { // where('column', array(1, 2)) => column IN (?,?)
            $placeholders = $this->makePlaceholders(count($parameters));
            $condition = "({$condition} IN ({$placeholders}))";
        }

        $this->_where_conditions[] = [
            'STATEMENT'   => $condition,
            'PARAMS'      => $parameters,
            'OPERATOR'    => $this->_and_or_operator
        ];
        // Reset the where operator to AND. To use OR, you must call _or()
        $this->_and();
        return $this;
    }

    /**
     * Create an AND operator in the where clause
     *
     * @return FluentModel
     */
    public function _and()
    {
        if ( $this->_wrap_open )
        {
            $this->_where_conditions[] = self::OPERATOR_AND;
            $this->_last_wrap_position = count($this->_where_conditions);
            $this->_wrap_open = false;
            return $this;
        }
        $this->_and_or_operator = self::OPERATOR_AND;
        return $this;
    }


    /**
     * Create an OR operator in the where clause
     *
     * @return FluentModel
     */
    public function _or()
    {
        if ( $this->_wrap_open )
        {
            $this->_where_conditions[] = self::OPERATOR_OR;
            $this->_last_wrap_position = count($this->_where_conditions);
            $this->_wrap_open = false;
            return $this;
        }
        $this->_and_or_operator = self::OPERATOR_OR;
        return $this;
    }

    /**
     * To group multiple where clauses together.
     *
     * @return FluentModel
     */
    public function wrap()
    {
        $this->_wrap_open = true;
        $spliced = array_splice($this->_where_conditions, $this->_last_wrap_position, count($this->_where_conditions), '(');
        $this->_where_conditions = array_merge($this->_where_conditions, $spliced);
        array_push($this->_where_conditions,')');
        $this->_last_wrap_position = count($this->_where_conditions);
        return $this;
    }

    /**
     * Where Primary key
     *
     * @param      $id
     * @param bool $addAlias
     *
     * @return FluentModel
     */
    public function wherePK($id, $addAlias=false)
    {
        $alias = $addAlias ? "{$this->_table_alias}." : '';
        return $this->where($alias . $this->getPrimaryKeyname(), $id);
    }

    /**
     * WHERE $columnName != $value
     *
     * @param  string   $columnName
     * @param  mixed    $value
     * @return FluentModel
     */
    public function whereNot($columnName, $value)
    {
        return $this->where("$columnName != ?", $value);
    }

    /**
     * WHERE $columnName LIKE $value
     *
     * @param  string   $columnName
     * @param  mixed    $value
     * @return FluentModel
     */
    public function whereLike($columnName, $value)
    {
        return $this->where("$columnName LIKE ?", $value);
    }

    /**
     * WHERE $columnName NOT LIKE $value
     *
     * @param  string   $columnName
     * @param  mixed    $value
     * @return FluentModel
     */
    public function whereNotLike($columnName, $value)
    {
        return $this->where("$columnName NOT LIKE ?", $value);
    }

    /**
     * WHERE $columnName > $value
     *
     * @param  string   $columnName
     * @param  mixed    $value
     * @return FluentModel
     */
    public function whereGt($columnName, $value)
    {
        return $this->where("$columnName > ?", $value);
    }

    /**
     * WHERE $columnName >= $value
     *
     * @param  string   $columnName
     * @param  mixed    $value
     * @return FluentModel
     */
    public function whereGte($columnName, $value)
    {
        return $this->where("$columnName >= ?", $value);
    }

    /**
     * WHERE $columnName < $value
     *
     * @param  string   $columnName
     * @param  mixed    $value
     * @return FluentModel
     */
    public function whereLt($columnName, $value)
    {
        return $this->where("$columnName < ?", $value);
    }

    /**
     * WHERE $columnName <= $value
     *
     * @param  string   $columnName
     * @param  mixed    $value
     * @return FluentModel
     */
    public function whereLte($columnName, $value)
    {
        return $this->where("$columnName <= ?", $value);
    }

    /**
     * WHERE $columnName IN (?,?,?,...)
     *
     * @param  string   $columnName
     * @param  Array    $values
     * @return FluentModel
     */
    public function whereIn($columnName, Array $values)
    {
        return $this->where($columnName,$values);
    }

    /**
     * WHERE $columnName NOT IN (?,?,?,...)
     *
     * @param  string   $columnName
     * @param  Array    $values
     * @return FluentModel
     */
    public function whereNotIn($columnName, Array $values)
    {
        $placeholders = $this->makePlaceholders(count($values));

        return $this->where("({$columnName} NOT IN ({$placeholders}))", $values);
    }

    /**
     * WHERE $columnName IS NULL
     *
     * @param  string   $columnName
     * @return FluentModel
     */
    public function whereNull($columnName)
    {
        return $this->where("({$columnName} IS NULL)");
    }

    /**
     * WHERE $columnName IS NOT NULL
     *
     * @param  string   $columnName
     * @return FluentModel
     */
    public function whereNotNull($columnName)
    {
        return $this->where("({$columnName} IS NOT NULL)");
    }


    public function having($statement, $operator = self::OPERATOR_AND)
    {
        $this->_is_fluent_query = true;
        $this->_having[] = [
            'STATEMENT'   => $statement,
            'OPERATOR'    => $operator
        ];
        return $this;
    }

    /**
     * ORDER BY $columnName (ASC | DESC)
     *
     * @param  string   $columnName - The name of the column or an expression
     * @param  string   $ordering   (DESC | ASC)
     * @return FluentModel
     */
    public function orderBy($columnName, $ordering = '')
    {
        $this->_is_fluent_query = true;
        $this->_order_by[] = "{$columnName} {$ordering}";
        return $this;
    }

    /**
     * GROUP BY $columnName
     *
     * @param  string   $columnName
     * @return FluentModel
     */
    public function groupBy($columnName)
    {
        $this->_is_fluent_query = true;
        $this->_group_by[] = $columnName;
        return $this;
    }


    /**
     * LIMIT $limit
     *
     * @param  int      $limit
     * @param  int      $offset
     * @return FluentModel
     */
    public function limit($limit, $offset = null)
    {
        $this->_is_fluent_query = true;
        $this->_limit = $limit;

        if($offset){
            $this->offset($offset);
        }

        return $this;
    }

    /**
     * Return the limit
     *
     * @return mixed
     */
    public function getLimit()
    {
        return $this->_limit;
    }

    /**
     * OFFSET $offset
     *
     * @param  int      $offset
     * @return FluentModel
     */
    public function offset($offset)
    {
        $this->_is_fluent_query = true;
        $this->_offset = $offset;
        return $this;
    }

    /**
     * Return the offset
     *
     * @return mixed
     */
    public function getOffset()
    {
        return $this->_offset;
    }


    /**
     * Build a join
     *
     * @param  mixed    $table         - The table name
     * @param  string   $constraint    -> id = profile.user_id
     * @param  string   $table_alias   - The alias of the table name
     * @param  string   $join_operator - LEFT | INNER | etc...
     * @return FluentModel
     */
    public function join($table, $constraint, $table_alias = null, $join_operator = '')
    {
        $this->_is_fluent_query = true;

        if( $table instanceof FluentModel )
        {
            $table = $table->getTablename();
        }
        $join  = $join_operator ? "{$join_operator} " : '';
        $join .= "JOIN {$table} ";
        $table_alias = is_null($table_alias) ? Utilities\Inflector::classify($table) : $table_alias;
        $join .= $table_alias ? "AS {$table_alias} " : '';
        $join .= "ON {$constraint}";
        $this->_join_sources[] = $join;
        return $this;
    }

    /**
     * Create a left join
     *
     * @param  string   $table
     * @param  string   $constraint
     * @param  string   $table_alias
     * @return FluentModel
     */
    public function leftJoin($table, $constraint, $table_alias=null)
    {
        return $this->join($table, $constraint, $table_alias,'LEFT');
    }


    /**
     * Return the build select query
     *
     * @return string
     */
    public function getSelectQuery()
    {
        if ( !count($this->_select_fields) )
        {
            $this->select('*');
        }
        foreach ( $this->_select_fields as $idx => $sCols )
        {
            if (  Utilities\StringUtils::startsWith('distinct ', trim(strtolower($sCols))) )
            {
                $this->_distinct = true;
                $this->_select_fields[$idx] = str_ireplace('distinct ', '', $sCols);
            }
        }
        $query  = 'SELECT ';
        $query .= $this->_distinct ? 'DISTINCT ' : '';
        $query .= implode(', ', $this->prepareColumns($this->_select_fields));
        $query .= " FROM {$this->_table_name}".($this->_table_alias ? " AS {$this->_table_alias}" : '');
        if ( count($this->_join_sources ) )
        {
            $query .= (' ').implode(' ',$this->_join_sources);
        }
        $query .= $this->getWhereString(); // WHERE
        if ( count($this->_group_by) )
        {
            $query .= ' GROUP BY ' . implode(', ', array_unique($this->_group_by));
        }
        if ( count($this->_order_by ) )
        {
            $query .= ' ORDER BY ' . implode(', ', array_unique($this->_order_by));
        }
        $query .= $this->getHavingString(); // HAVING
        if ( $this->_limit )
        {
            $query .= ' LIMIT ' . $this->_limit;
        }
        if ( $this->_offset )
        {
            $query .= ' OFFSET ' . $this->_offset;
        }
        return $query;
    }

    /**
     * Prepare columns to include the table alias name
     * @param array $columns
     * @return array
     */
    private function prepareColumns(array $columns)
    {
        if ( ! $this->_table_alias )
        {
            return $columns;
        }
        $newColumns = [];
        foreach ($columns as $column)
        {
            if ( strpos($column, ',') )
            {
                $newColumns = array_merge($this->prepareColumns(explode(',', $column)), $newColumns);
            }
            elseif ( preg_match('/^(AVG|SUM|MAX|MIN|COUNT)/', $column) )
            {
                $newColumns[] = trim($column);
            }
            elseif (strpos($column, '.') == false && strpos(strtoupper($column), 'NULL') == false)
            {
                $column         = trim($column);
                $newColumns[]   = preg_match('/^[0-9]/', $column) ? trim($column) : "{$this->_table_alias}.{$column}";
            }
            else
            {
                $newColumns[] = trim($column);
            }
        }
        return $newColumns;
    }

    /**
     * Build the WHERE clause(s)
     *
     * @return string
     */
    protected function getWhereString()
    {
        // If there are no WHERE clauses, return empty string
        if ( ! count($this->_where_conditions) )
        {
            return ' WHERE 1';
        }

        $where_condition = '';
        $last_condition = '';

        foreach ( $this->_where_conditions as $condition )
        {
            if ( is_array($condition) )
            {
                if ($where_condition && $last_condition != '(' && !preg_match('/\)\s+(OR|AND)\s+$/i', $where_condition))
                {
                    $where_condition .= $condition['OPERATOR'];
                }
                $where_condition .= $condition['STATEMENT'];
                $this->_where_parameters = array_merge($this->_where_parameters, $condition['PARAMS']);
            }
            else
            {
                $where_condition .= $condition;
            }
            $last_condition = $condition;
        }
        return " WHERE {$where_condition}" ;
    }

    /**
     * Return the HAVING clause
     *
     * @return string
     */
    protected function getHavingString()
    {
        // If there are no WHERE clauses, return empty string
        if ( ! count($this->_having) )
        {
            return '';
        }

        $having_condition = '';

        foreach ( $this->_having as $condition )
        {
            if ( is_array($condition) )
            {
                if ( $having_condition && !preg_match('/\)\s+(OR|AND)\s+$/i', $having_condition) )
                {
                    $having_condition .= $condition['OPERATOR'];
                }
                $having_condition .= $condition['STATEMENT'];
            }
            else
            {
                $having_condition .= $condition;
            }
        }
        return " HAVING {$having_condition}" ;
    }

    /**
     * Return the values to be bound for where
     *
     * @return Array
     */
    protected function getWhereParameters()
    {
        return $this->_where_parameters;
    }

    /**
      * Detect if its a single row instance and reset it to PK
      *
      * @return FluentModel
      */
    protected function setSingleWhere()
    {
        if ( $this->_is_single )
        {
            $this->resetWhere();
            $this->wherePK($this->getPK());
        }
        return $this;
    }

    /**
      * Reset the where
      *
      * @return FluentModel
      */
    protected function resetWhere()
    {
        $this->_where_conditions = [];
        $this->_where_parameters = [];
        return $this;
    }


/*------------------------------------------------------------------------------
                                Insert
*-----------------------------------------------------------------------------*/
    /**
     * Insert new rows
     * $data can be 2 dimensional to add a bulk insert
     * If a single row is inserted, it will return it's row instance
     *
     * @param  array    $data - data to populate
     * @return FluentModel
     */
    public function insert($data)
    {
        $data           = !is_array($data) ? (array)$data : $data;
        $insert_values  = [];
        $question_marks = [];
        // check if the data is multi dimension for bulk insert
        $multi          = $this->isArrayMultiDim($data);
        $datafield      = array_keys($multi ? $data[0] : $data);
        foreach ( ($multi ? $data : [$data]) as $d )
        {
            $d                  = $this->beforeSave($d, static::SAVE_INSERT);
            $datafield          = array_keys($d);
            $question_marks[]   = '('  . $this->makePlaceholders(count($d)) . ')';
            $insert_values      = array_merge($insert_values, array_values($d));
        }
        $sql = "INSERT INTO {$this->_table_name} (" . implode(',', $datafield ) . ') ';
        $sql .= 'VALUES ' . implode(',', $question_marks);
        $this->query($sql, $insert_values);
        foreach ( ($multi ? $data : [$data]) as $d )
        {
            $this->afterSave($d, static::SAVE_INSERT);
        }
        // Return the SQL Query
        if ($this->_debug_sql_query)
        {
            $this->debugSqlQuery(false);
            return $this;
        }
        $rowCount = $this->rowCount();
        // On single element return the object
        if ( $rowCount === 1 )
        {
            $primaryKeyname         = $this->getPrimaryKeyname();
            $data[$primaryKeyname]  = $this->_pdo->lastInsertId($primaryKeyname);
            return $this->fromArray($data);
        }
        return $rowCount;
    }

    /**
     * @param array $data
     * @param array $match_on
     *
     * @return Array|bool|int|null
     */
    public function upsert(array $data, array $match_on = null)
    {
        $data           = !is_array($data) ? (array)$data : $data;
        $is_multi       = ( count($data) != count($data, COUNT_RECURSIVE) );
        $data           = $is_multi ? $data : [$data];
        $num_success    = 0;
        $result         = null;
        foreach ( $data as $row )
        {
            if ( ($result = $this->upsertOne($row, $match_on) ) )
            {
                $num_success++;
            }
        }
        return $is_multi ? $num_success : $result;
    }

    public function upsertOne(array $data, $match_on=[])
    {
        $data           = is_array($data) ? $data : (array)$data;
        $primary_key    = $this->getPrimaryKeyname();
        $match_on       = empty($match_on) && isset($data[$primary_key]) ? [$primary_key] : $match_on;
        foreach ( (array)$match_on as $column )
        {
            \Assert\that(! isset($data[$column]) && $column !== $primary_key)->false('The match on value for upserts is missing.');
            if ( isset($data[$column]) )
            {
                $this->where($column, $data[$column]);
            }
        }
        if ( count($this->_where_conditions) < 1 )
        {
            $result = $this->insert($data);
            return $result ? $result->toArray() : false;
        }
        $oResult = $this->findOne();
        if ( $oResult )
        {
            $oResult->update($data);
            $this->_errors = $oResult->errors();
            return $oResult->toArray();
        }
        $result = $this->insert($data);
        return $result ? $result->toArray() : false;
    }

    public function beforeSave(array $data, $type)
    {
        $data = $this->removeInvalidDataFields($data);
        return $this->applyDefaults($data, $type);
    }

    /**
     * @param array $data
     *
     * @return array
     */
    public function afterSave(array $data, $type)
    {
        return $data;
    }

    /**
     * @param array $data
     *
     * @return array
     */
    public function afterFind(array $data)
    {
        return $data;
    }

    /**
     * Apply some default fields
     *
     * @param $data
     * @param $type
     *
     * @return mixed
     */
    protected function applyDefaults($data, $type)
    {
        return $data;
    }

    public function removeInvalidDataFields($data)
    {
        $columns = $this->columns();
        if ( empty($columns) )
        {
            return $data;
        }
        return array_intersect_key($data, $columns);
    }

    public function pagingMeta()
    {
        $model     = clone $this;
        $limit     = intval($this->getLimit());
        $offset    = intval($this->getOffset());
        $total     = intval($model->offset(null)->limit(null)->count());
        $aOrderBy   = !is_array($this->order_by) ? [] : $this->order_by;
        $aPagingMeta = [
            'items'     => $limit,
            'page'      => $offset === 0 ? 1 : intval( $offset / $limit ) + 1,
            'pages'     => $limit === 0 ? 1 : intval(ceil($total / $limit)),
            'order'     => $aOrderBy,
            'total'     => $total,
            'filters'   => $this->_filter_meta,
            'fields'    => $this->_requested_fields,
        ];
        return $aPagingMeta;
    }

    public function getQueryMeta()
    {
        return [
            'limit'     => $this->getLimit(),
            'offset'    => $this->getOffset(),
            'next'      => null,
            'previous'  => null,
        ];
    }

/*------------------------------------------------------------------------------
                                Updating
*-----------------------------------------------------------------------------*/
    /**
      * Update entries
      * Use the query builder to create the where clause
      *
      * @param Array $data the data to update
      * @return int - total affected rows
      */
    public function update(array $data = null)
    {
        $data = !is_array($data) ? (array)$data : $data;
        $this->setSingleWhere();
        if ( ! is_null($data) )
        {
            $this->set($data);
        }
        if ( ! ( $this->_dirty_fields = $this->beforeSave($this->_dirty_fields, static::SAVE_UPDATE) ) )
        {
            return false;
        }
        if ( ! $this->validate($this->_dirty_fields, __FUNCTION__) )
        {
            return false;
        }
        // Make sure we remove the primary key
        unset($this->_dirty_fields[$this->getPrimaryKeyname()]);
        $values     = array_values($this->_dirty_fields);
        $field_list = [];
        if ( count($values) == 0 )
        {
            return false;
        }
        foreach ( array_keys($this->_dirty_fields) as $key )
        {
            $field_list[] = "{$key} = ?";
        }
        $query  = "UPDATE {$this->_table_name} SET ";
        $query .= implode(', ', $field_list);
        $query .= $this->getWhereString();
        $values = array_merge($values, $this->getWhereParameters());
        $this->query($query, $values);
        $this->afterSave($data, static::SAVE_UPDATE);
        // Return the SQL Query
        if ($this->_debug_sql_query)
        {
            $this->debugSqlQuery(false);
            return $this;
        }
        $this->_dirty_fields = [];
        return $this->rowCount();
    }

/*------------------------------------------------------------------------------
                                Delete
*-----------------------------------------------------------------------------*/
    /**
     * Delete rows
     * Use the query builder to create the where clause
     * @param bool $deleteAll = When there is no where condition, setting to true will delete all
     * @return int - total affected rows
     */
    public function delete($deleteAll = false)
    {
        $this->setSingleWhere();
        $query  = "DELETE FROM {$this->_table_name}";
        if ( count($this->_where_conditions) )
        {
            $query .= $this->getWhereString();
            $this->query($query, $this->getWhereParameters());
        }
        else
        {
            if ( ! $deleteAll )
            {
                return false;
            }
            $this->query($query);
        }
        // Return the SQL Query
        if ( $this->_debug_sql_query )
        {
            $this->debugSqlQuery(false);
            return $this;
        }
        return $this->rowCount();
    }

    /**
     * Truncate the table
     * @return int
     */
    public function truncate()
    {
        return $this->_pdo->exec("TRUNCATE TABLE {$this->_table_name}");
    }

/*------------------------------------------------------------------------------
                                Set & Save
*-----------------------------------------------------------------------------*/
    /**
     * To set data for update or insert
     * $key can be an array for mass set
     *
     * @param  mixed    $key
     * @param  mixed    $value
     * @return FluentModel
     */
    public function set($key, $value = null)
    {
        if ( is_array($key) )
        {
            foreach ( $key as $keyKey => $keyValue )
            {
                $this->set($keyKey, $keyValue);
            }
            return $this;
        }
        if ( $key != $this->getPrimaryKeyname() )
        {
            $this->_data[$key]          = $value;
            $this->_dirty_fields[$key]  = $value;
        }
        return $this;
    }

    /**
     * Save, a shortcut to update() or insert().
     *
     * @return mixed
     */
    public function save()
    {
        if ( $this->_is_single || count($this->_where_conditions) )
        {
            return $this->update();
        }
        return $this->insert($this->_dirty_fields);
    }


/*------------------------------------------------------------------------------
                                AGGREGATION
*-----------------------------------------------------------------------------*/

    /**
     * Return the aggregate count of column
     *
     * @param  string $column - the column name
     * @return double
     */
    public function count($column='*')
    {
        return $this->aggregate("COUNT({$column})");
    }

    /**
     * Return the aggregate max count of column
     *
     * @param  string $column - the column name
     * @return double
     */
    public function max($column)
    {
        return $this->aggregate("MAX({$column})");
    }


    /**
     * Return the aggregate min count of column
     *
     * @param  string $column - the column name
     * @return double
     */
    public function min($column)
    {
        return $this->aggregate("MIN({$column})");
    }

    /**
     * Return the aggregate sum count of column
     *
     * @param  string $column - the column name
     * @return double
     */
    public function sum($column)
    {
        return $this->aggregate("SUM({$column})");
    }

    /**
     * Return the aggregate average count of column
     *
     * @param  string $column - the column name
     * @return double
     */
    public function avg($column)
    {
        return $this->aggregate("AVG({$column})");
    }

    /**
     *
     * @param  string $fn - The function to use for the aggregation
     * @return double
     */
    public function aggregate($fn)
    {
        $this->select($fn, 'count');
        $result = $this->findOne();
        return ($result !== false && isset($result->count)) ? $result->count : 0;
    }

/*------------------------------------------------------------------------------
                                Access single entry data
*-----------------------------------------------------------------------------*/
    /**
     * Return the primary key
     *
     * @return int
     */
    public function getPK()
    {
        return $this->get($this->getPrimaryKeyname());
    }

    /**
     * Get the a key
     *
     * @param  string $key
     * @return mixed
     */
    public function get($key)
    {
        return isset($this->_data[$key]) ? $this->_data[$key] : null;
    }

    /**
     * Return the raw data of this single instance
     *
     * @return Array
     */
    public function toArray()
    {
        return $this->_data;
    }

    public function toObject($iItemId=null)
    {
        if ( is_null($iItemId) && $this->_pdo_executed )
        {
            return (object)$this->_data;
        }
        $aResult = $this->findOne($iItemId);
        if ( !$aResult )
        {
            return null;
        }
        return (object)$aResult->toArray();
    }

    public function __get($key)
    {
        return $this->get($key);
    }

    public function __set($key, $value)
    {
        $this->set($key, $value);
    }

    public function __isset($key)
    {
        return isset($this->_data[$key]);
    }

/*******************************************************************************/

    /**
     * To dynamically call a table
     *
     * $VoodOrm = new VoodOrm($myPDO);
     * on table 'users'
     * $Users = $VoodOrm->table('users');
     *
     * Or to call a table relationship
     * on table 'photos' where users can have many photos
     * $allMyPhotos = $Users->findOne(1234)->photos();
     *
     * On relationship, it is faster to do eager load (VoodOrm::REL_HASONE | VoodOrm::REL_HASMANY)
     * All the data are loaded first than queried after. Eager load does one round to the table.
     * Lazy load will do multiple round to the table.
     *
     * @param  string $tablename
     * @param  Array $args
     *      relationship
     *      foreignKey
     *      localKey
     *      where
     *      sort
     *      callback
     *      model
     *      backref
     * @return \ArrayIterator | Object | Null
     */

    public function __call($tablename, $args)
    {
        $_def = [
            'relationship' => self::HAS_MANY, // The type of association: HAS_MANY | HAS_ONE
            'foreignKey' => '', // the foreign key for the association
            'localKey' => '', // localKey for the association
            'where' => [], // Where condition
            'sort' => '', // Sort of the result
            'callback' => null, // A callback on the results
            'model' => null, // An instance VoodOrm class as the class to interact with
            'backref' => false // When true, it will query in the reverse direction
        ];
        $prop = array_merge($_def, $args);
        if ( !$this->_is_single )
        {
            return $prop['model'] ?: $this->table($tablename);
        }

        switch ($prop['relationship'])
        {
            /**
             * OneToMany
             */
            default:
            case self::HAS_MANY:

                $localKeyN      = ($prop['localKey']) ?: $this->getPrimaryKeyname();
                $foreignKeyN    = ($prop['foreignKey']) ?: $this->getForeignKeyname();
                $token          = $this->tokenize($tablename, $foreignKeyN.':'.$prop['relationship']);
                if ( isset(self::$_references[$token]) )
                {
                    return isset(self::$_references[$token][$this->{$localKeyN}]) ? self::$_references[$token][$this->{$localKeyN}]  : [];
                }
                $model = $prop['model'] ?: $this->table($tablename);
                // Backref (back reference). Reverse the query
                if ($prop['backref'])
                {
                    if ( isset($this->_reference_keys[$foreignKeyN]) )
                    {
                        $model->where($localKeyN, $this->_reference_keys[$foreignKeyN]);
                    }
                }
                else
                {
                    if (isset($this->_reference_keys[$localKeyN]))
                    {
                        $model->where($foreignKeyN, $this->_reference_keys[$localKeyN]);
                    }
                }
                if( $prop['where'] )
                {
                    $model->where($prop['where']);
                }
                if ( $prop['sort'] )
                {
                    $model->orderBy($prop['sort']);
                }

                self::$_references[$token] = $model->find(function($rows) use ($model, $foreignKeyN, $prop) {

                    $results = [];
                    /* @var array $results */
                    foreach ($rows as $row)
                    {
                        if ( !isset($results[$row[$foreignKeyN]]) )
                        {
                            $results[$row[$foreignKeyN]] = [];
                        }
                        $results[$row[$foreignKeyN]][]= (is_callable($prop['callback'])
                                                            ? $prop['callback']($row)
                                                            : $model->fromArray($row));
                    }
                    return $results;
                });
                return isset(self::$_references[$token][$this->{$localKeyN}])
                            ? self::$_references[$token][$this->{$localKeyN}]
                            : [];
                break;

            case self::HAS_ONE:

                $localKeyN = $prop['localKey'] ?: $this->formatKeyname($this->getStructure()['foreignKeyname'], $tablename);
                if ( !isset($this->{$localKeyN}) || !$this->{$localKeyN} ) {

                    return null;
                }
                $model          = $prop['model'] ?: $this->table($tablename);
                $foreignKeyN    = $prop['foreignKey'] ?: $model->getPrimaryKeyname();
                $token          = $this->tokenize($tablename, $localKeyN . ':' . $prop['relationship']);
                if ( isset(self::$_references[$token]) ) {

                    return self::$_references[$token][$this->{$localKeyN}];
                }
                if (isset($this->_reference_keys[$localKeyN])) {
                   $model->where($foreignKeyN, $this->_reference_keys[$localKeyN]);
                }
                // $callback isn't set
                $callback = $prop['callback'];
                self::$_references[$token] = $model->find(function($rows) use ($model, $callback, $foreignKeyN) {
                   $results = [];
                   foreach ($rows as $row) {
                        $results[$row[$foreignKeyN]] = is_callable($callback)  ? $callback($row) : $model->fromArray($row);
                   }
                   return $results;
                });
                return self::$_references[$token][$this->{$localKeyN}];
                break;
        }
    }

    public function columns($keys_only=false)
    {
        return $keys_only ? array_keys($this->_columns) : $this->_columns;
    }

    public function skeleton()
    {
        $aSkeleton = [];
        foreach ( $this->_columns as $column => $sType )
        {
            $aSkeleton[$column] = null;
        }
        return $aSkeleton;
    }

    public function getWhereIn($field, array $values, $type='string')
    {
        \Assert\that($field)->string()->notEmpty();
        \Assert\that($values)->isArray();
        \Assert\that($type)->inArray(['string', 'float', 'integer']);
        if ( $type !== 'string' )
        {
            \Assert\that($values)->numeric();
        }
        if ( empty($values) )
        {
            return '';
        }
        foreach ( $values as $idx => $value )
        {
            switch ( $type )
            {
                case 'string':
                    $values[$idx] = $this->_pdo->quote(\PDO::PARAM_STR);
                    break;
                case 'integer':
                case 'float':
                    $values[$idx] = $this->_pdo->quote(\PDO::PARAM_INT);
                    break;
            }
        }
        $sValues = implode(',', $values);
        return <<<SQL
          AND {$field} IN ({$sValues})
SQL;
    }

    public function paginate(array $query=[])
    {
        $query = (object)$query;
        if ( isset($query->_items) && is_numeric($query->_items) )
        {
            $this->limit($query->_items);
            if ( isset($query->_page) && is_numeric($query->_page) )
            {
                $this->offset(($query->_page - 1) * $query->_items);
            }
        }
        $columns = $this->columns();
        if ( !empty($query->_order) && isset($columns[$query->_order]) )
        {
            $this->orderBy($query->_order);
        }
        if ( !empty($query->_fields) )
        {
            $select_fields     = [];
            $query->_fields    = is_array($query->_fields) ? $query->_fields : explode('|', $query->_fields);
            foreach ( $query->_fields as $idx => $field )
            {
                $alias = Utilities\StringUtils::before(':', $field);
                $alias = !empty($alias) ? $alias : $this->_table_alias;
                if ( $alias !== $this->_table_alias )
                {
                    throw new \Exception("Joined table aliases not supported yet");
                }
                $field = Utilities\StringUtils::after(':', $field, true);
                $field = $field === '_display_field' ? $this->_display_column : $field;
                if ( isset($columns[$field]) )
                {
                    $select_fields[] = "{$alias}.{$field}";
                }
            }
            if ( !empty($select_fields) )
            {
                $this->select($select_fields);
                $this->_aRequestedFields = $select_fields;
            }
        }
        return $this;
    }

    public function filter(array $query=[])
    {
        $columns   = $this->columns();
        $alias     = '';
        foreach ( $query as $column => $sValue )
        {
            $alias = Utilities\StringUtils::before(':', $column);
            $alias = !empty($alias) ? $alias : $this->_table_alias;
            if ( $alias !== $this->_table_alias )
            {
                throw new \Exception("Joined table aliases not supported yet");
            }
            $field = Utilities\StringUtils::after(':', $column, true);
            $field = $field === '_display_field' ? $this->_display_column : $field;
            if ( isset($columns[$field]) && !empty($sValue) )
            {
                $column = "{$alias}.{$field}";
                $this->_aFilterMeta[$column] = $sValue;
                if ( mb_stripos($sValue, '|') !== false )
                {
                    $this->whereIn($column, explode('|', $sValue));
                }
                else
                {
                    $this->where($column, $sValue);
                }
            }
        }

        if ( !empty($query['_search']) )
        {
            $aStringColumns = array_filter($columns, function($sType){
                return in_array($sType, ['varchar', 'text', 'enum']);
            });
            $aWhereLikes = [];
            foreach ( $aStringColumns as $column => $sType )
            {
                $column        = "{$alias}.{$column}";
                $aSearchTerms   = explode('|', $query['_search']);
                foreach ( $aSearchTerms as $sTerm )
                {
                    $aWhereLikes[$column] = "%{$sTerm}%";
                }
            }
            if ( empty($aWhereLikes) )
            {
                return $this;
            }
            $this->where([1=>1])->wrap()->_and();
            foreach ( $aWhereLikes as $column => $sTerm )
            {
                $this->_or()->whereLike($column, $sTerm);
            }
            $this->wrap();
        }
        return $this;
    }

/*******************************************************************************/
// Utilities methods

    /**
     * Reset fields
     *
     * @return FluentModel
     */
    public function reset()
    {
        $this->_where_parameters    = [];
        $this->_select_fields       = ['*'];
        $this->_join_sources        = [];
        $this->_where_conditions    = [];
        $this->_limit               = null;
        $this->_offset              = null;
        $this->_order_by            = [];
        $this->_group_by            = [];
        $this->_data                = [];
        $this->_dirty_fields        = [];
        $this->_is_fluent_query     = true;
        $this->_and_or_operator     = self::OPERATOR_AND;
        $this->_having              = [];
        $this->_wrap_open           = false;
        $this->_last_wrap_position  = 0;
        $this->_debug_sql_query     = false;
        $this->_pdo_stmt            = null;
        $this->_is_single           = false;
        $this->_distinct            = false;
        $this->_requested_fields    = null;
        $this->_filter_meta         = null;
        return $this;
    }

    /**
     * Return a YYYY-MM-DD HH:II:SS date format
     *
     * @param string $datetime - An english textual datetime description
     *          now, yesterday, 3 days ago, +1 week
     *          http://php.net/manual/en/function.strtotime.php
     * @return string YYYY-MM-DD HH:II:SS
     */
    public static function NOW($datetime = 'now')
    {
        return (new DateTime($datetime ?: 'now'))->format('Y-m-d H:i:s');
    }


/*******************************************************************************/
// Query Debugger

    /**
     * To debug the query. It will not execute it but instead using debugSqlQuery()
     * and getSqlParameters to get the data
     *
     * @param bool $bool
     * @return FluentModel
     */
    public function debugSqlQuery($bool = true)
    {
        $this->_debug_sql_query = $bool;
        return $this;
    }

    /**
     * Get the SQL Query with
     *
     * @return string
     */
    public function getSqlQuery()
    {
        return $this->_sql_query;
    }

    /**
     * Return the parameters of the SQL
     *
     * @return array
     */
    public function getSqlParameters()
    {
        return $this->_sql_parameters;
    }

/*******************************************************************************/
    /**
     * Return a string containing the given number of question marks,
     * separated by commas. Eg '?, ?, ?'
     *
     * @param int - total of placeholder to insert
     * @return string
     */
    protected function makePlaceholders($number_of_placeholders=1)
    {
        return implode(', ', array_fill(0, $number_of_placeholders, '?'));
    }

    /**
     * Format the table{Primary|Foreign}KeyName
     *
     * @param  string $pattern
     * @param  string $tablename
     * @return string
     */
    protected function formatKeyname($pattern, $tablename)
    {
       return sprintf($pattern,$tablename);
    }

    /**
     * To create a string that will be used as key for the relationship
     *
     * @param  mixed   $key
     * @param  string   $suffix
     * @return string
     */
    private function tokenize($key, $suffix = '')
    {
        return  "{$this->_table_token}:{$key}:{$suffix}";
    }

    public function __clone()
    {
    }

    public function __toString()
    {
        return $this->_is_single ? $this->getPK() : (string)$this->_table_name;
    }

    /**
     * Check if array is multi dim
     * @param array $data
     * @return bool
     */
    private function isArrayMultiDim(Array $data)
    {
        return (count($data) != count($data, COUNT_RECURSIVE));
    }

    /**
     * Get mysql time
     *
     * @return string
     * @throws \Exception
     */
    public function getMysqlCurrentTime()
    {
        $oStmt = $this->query('SELECT NOW()', [], true);
        return $oStmt->fetchColumn();
    }
}
