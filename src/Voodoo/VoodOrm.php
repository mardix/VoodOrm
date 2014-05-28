<?php
/**
 * -----------------------------------------------------------------------------
 * VoodOrm
 * -----------------------------------------------------------------------------
 * @author      Mardix (http://twitter.com/mardix)
 * @github      https://github.com/mardix/VoodOrm
 * @package     VoodooPHP (https://github.com/mardix/Voodoo/)
 *
 * @copyright   (c) 2014 Mardix (http://github.com/mardix)
 * @license     MIT
 * -----------------------------------------------------------------------------
 *
 * About VoodOrm
 *
 * VoodOrm is a micro-ORM which functions as both a fluent select query API and a CRUD model class.
 * VoodOrm is built on top of PDO and is well fit for small to mid-sized projects, where the emphasis 
 * is on simplicity and rapid development rather than infinite flexibility and features.
 * VoodOrm works easily with table relationship.
 * 
 * Learn more: https://github.com/mardix/VoodOrm
 * 
 * 3.0
 *  - Associationship 
 *      - Better association with where, sort, etc...
 *  New method:
 *      on() - To create a Join with another VoodOrm instance  
 * Change
 *      - limit() - return the value if no argument is provided
 *      - offset() - return the value if no arg is provided
 *      _or -> _or_
 *      _and -> _and_
 * Use SplFixedArray instead of ArrayIterator. Improve memory
 * Table's column are prefixed with the table alias
 * Table Alias is set as the table if not set
 * Added token for %this and %join - %this and %join translate to the name of the alias
 */

namespace Voodoo;

use ArrayIterator,
    SplFixedArray,
    IteratorAggregate,
    Closure,
    PDO,
    DateTime;

class VoodOrm implements IteratorAggregate
{
    const NAME              = "VoodOrm";
    const VERSION           = "3.0-b";

    // ASSOCIATION CONSTANT
    const ASSO_ONE        =  1;      // OneToOne. Eager Load data
    const ASSO_MANY       =  2;      // OneToMany. Eager load data

    const OPERATOR_AND  = " AND ";
    const OPERATOR_OR   = " OR ";
    const ORDERBY_ASC   = "ASC";
    const ORDERBY_DESC  = "DESC";
    const JOIN_LEFT     = "LEFT";
    const JOIN_RIGHT    = "RIGHT";
    const EOL           = "\n";
    const TAB           = "\t";
    const EOL_TAB       = "\n\t";
    
    protected $pdo = null;
    protected $table_name = "";
    protected $table_token = "";
    protected $table_alias = "";    
    private $pdo_stmt = null;

    protected $is_single = false;
    private $select_fields = [];
    private $join_sources = [];
    private $limit = null;
    private $offset = null;
    private $order_by = [];
    private $group_by = [];
    private $where_parameters = [];
    private $where_conditions = [];
    private $and_or_operator = self::OPERATOR_AND;
    private $having = [];
    private $wrap_open = false;
    private $last_wrap_position = 0;
    private $is_fluent_query = true;
    private $pdo_executed = false;
    private $_data = [];
    private $debug_sql_query = false;
    private $sql_query = "";
    private $sql_parameters = [];
    private $_dirty_fields = [];
    private $reference_keys = [];
    private $join_on = false;
    private static $references = []; 
    
    // Table structure
    public $table_structure = [
        "primaryKeyname"    => "id",
        "foreignKeyname"    => "%s_id"
    ];

/*******************************************************************************/

    /**
     * Constructor & set the table structure
     *
     * @param PDO    $pdo            - The PDO connection
     * @param string $primaryKeyName - Structure: table primary. If its an array, it must be the structure
     * @param string $foreignKeyName - Structure: table foreignKeyName.
     *                  It can be like %s_id where %s is the table name
     */
    public function __construct(PDO $pdo, $primaryKeyName = "id", $foreignKeyName = "%s_id") 
    {
        $this->pdo = $pdo;
        $this->setStructure($primaryKeyName, $foreignKeyName);
    }

    /**
     * Define the working table and create a new instance
     *
     * @param  string   $tableName - Table name
     * @param  string   $alias     - The table alias name
     * @return Voodoo\VoodOrm
     */
    public function table($tableName, $alias = "")
    {
        $instance = clone($this);
        $instance->table_name = $tableName;
        $instance->table_token = $tableName;
        $instance->setTableAlias($alias ?: $tableName);
        $instance->reset();
        return $instance;        
    }

    /**
     * Return the name of the table
     * @return string
     */
    public function getTablename(){
        return $this->table_name;
    }
    
    /**
     * Set the table alias
     *
     * @param string $alias
     * @return Voodoo\VoodOrm
     */
    public function setTableAlias($alias)
    {
        $this->table_alias = $alias;
        return $this;
    }

    public function getTableAlias()
    {
        return $this->table_alias;
    }
    
    /**
     * 
     * @param string $primaryKeyName - the primary key, ie: id
     * @param string $foreignKeyName - the foreign key as a pattern: %s_id, 
     *                                  where %s will be substituted with the table name
     * @return \Voodoo\VoodOrm
     */
    public function setStructure($primaryKeyName = "id", $foreignKeyName = "%s_id")
    {
        $this->table_structure = [
            "primaryKeyname" => $primaryKeyName,
            "foreignKeyname" => $foreignKeyName
        ];
        return $this;
    }
    
    /**
     * Return the table stucture
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
        return $this->formatKeyname($this->table_structure["primaryKeyname"], $this->table_name);
    }
    
    /**
     * Get foreign key name
     * @return string
     */
    public function getForeignKeyname()
    {
        return $this->formatKeyname($this->table_structure["foreignKeyname"], $this->table_name);
    }
    
    /**
     * Return if the entry is of a single row
     * 
     * @return bool
     */
    public function isSingleRow()
    {
        return $this->is_single;
    }
    
/*******************************************************************************/
    /**
     * To execute a raw query
     * 
     * @param string    $query
     * @param Array     $parameters
     * @param bool      $return_as_pdo_stmt - true, it will return the PDOStatement
     *                                       false, it will return $this, which can be used for chaining
     *                                              or access the properties of the results
     * @return VoodOrm | PDOStatement
     */
    public function query($query, Array $parameters = [], $return_as_pdo_stmt = false)
    {
        $this->sql_parameters = $parameters;
        $this->sql_query = $query;

        if ($this->debug_sql_query) {
            return $this;
        } else {
            $this->pdo_stmt = $this->pdo->prepare($query);
            $this->pdo_executed = $this->pdo_stmt->execute($parameters);
            if ($return_as_pdo_stmt) {
                return $this->pdo_stmt;
            } else {
                $this->is_fluent_query = true;
                return $this;
            }        
        }
    }
    
    /**
     * Return the number of affected row by the last statement
     *
     * @return int
     */
    public function rowCount()
    {
        return ($this->pdo_executed == true) ? $this->pdo_stmt->rowCount() : 0;
    }


/*------------------------------------------------------------------------------
                                Querying
*-----------------------------------------------------------------------------*/
    /**
     * To find all rows and create their instances
     * Use the query builder to build the where clause or $this->query with select
     * If a callback function is provided, the 1st arg must accept the rows results
     *
     * $this->find(function($rows){
     *   // do more stuff here...
     * });
     *
     * @param  Closure        $callback - run a function on the returned rows
     * @return SplFixedArray
     */
    public function find(Closure $callback = null)
    {
        if($this->is_fluent_query && $this->pdo_stmt == null){
            $this->query($this->getSelectQuery(), $this->getWhereParameters());
        }
        
        //Debug SQL Query
        if ($this->debug_sql_query) {
            return $this->getSqlQuery();
        }
        
        if ($this->pdo_executed == true) {
            $allRows = $this->pdo_stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->reset();
            if (is_callable($callback)) {
                return $callback($allRows);
            } else {
                if(count($allRows)) {
                    // Holding all foreign keys matching the structure
                    $matchForeignKey = function($key) {
                        return preg_match("/".str_replace("%s","[a-z]", $this->table_structure["foreignKeyname"])."/i", $key);  
                    };
                    foreach ($allRows as $index => &$row) {
                        if ($index == 0) {
                            $this->reference_keys = [$this->table_structure["primaryKeyname"] => []];
                            foreach(array_keys($row) as $_rowK) {
                                if ($matchForeignKey($_rowK)) {
                                    $this->reference_keys[$_rowK] = [];
                                }
                            }
                        }
                        foreach($row as $rowK => &$rowV) {
                            if(array_key_exists($rowK, $this->reference_keys)) {
                                $this->reference_keys[$rowK][] = $rowV;
                                $this->reference_keys[$rowK] = array_unique($this->reference_keys[$rowK]);
                            }
                        }
                    }
                    unset($row);
                    $rows = [];
                    foreach ($allRows as $row) {
                        $rows[] = $this->fromArray($row);
                    }
                    $splFA = SplFixedArray::fromArray($rows); 
                    unset($rows);
                    return $splFA;
                }
                return new ArrayIterator;           
            }
        } else {
            return false;
        }       
             
    }
    
    /**
     * Return one row
     *
     * @param  int      $id - use to fetch by primary key
     * @return Voodoo\VoodOrm 
     */
    public function findOne($id = null)
    {
        if ($id){
            $this->wherePK($id);
        }
        $this->limit(1);
        // Debug the SQL Query
        if ($this->debug_sql_query) {
            $this->find();
            return false;
        } else {
            $findAll = $this->find();
            return $findAll->valid() ? $findAll->offsetGet(0) : false;
        }
    }
    
    /**
     * This method allow the iteration inside of foreach()
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
      return ($this->is_single) ? new ArrayIterator($this->toArray()) : $this->find();
    }
    
    /**
     * Create an instance from the given row (an associative
     * array of data fetched from the database)
     *
     * @return Voodoo\VoodOrm 
     */
    public function fromArray(Array $data)
    {
        $row  = clone($this);
        $row->reset();
        $row->is_single = true;
        $row->_data = $data;
        return $row;
    }

/*------------------------------------------------------------------------------
                                Fluent Query Builder
*-----------------------------------------------------------------------------*/

    /**
     * Create the select clause
     *
     * @param  mixed    $expr  - the column to select. Can be string or array of fields
     * @param  string   $alias - an alias to the column
     * @return Voodoo\VoodOrm 
     */
    public function select($columns = "*", $alias = null)
    {
        $this->is_fluent_query = true;
        if ($alias && !is_array($columns)){
            $columns .= " AS {$alias} ";
        }
        if(is_array($columns)){
            $this->select_fields = array_merge($this->select_fields, $columns);
        } else {
            $this->select_fields[] = $columns;
        }
        return $this;
    }

    /**
     * Add where condition, more calls appends with AND
     *
     * @param string condition possibly containing ? or :name
     * @param mixed array accepted by PDOStatement::execute or a scalar value
     * @param mixed ...
     * @return Voodoo\VoodOrm 
     */
    public function where($condition, $parameters = [])
    {
        $this->is_fluent_query = true;
        
        // By default the and_or_operator and wrap operator is AND, 
        if ($this->wrap_open || ! $this->and_or_operator) {
            $this->_and_();
        } 
        
        // where(array("column1" => 1, "column2 > ?" => 2))
        if (is_array($condition)) {
            foreach ($condition as $key => $val) {
                $this->where($key, $val);
            }
            return $this;
        }
        $column = $condition;
        $args = func_num_args();
        if ($args != 2 || strpbrk($condition, "?:")) { // where("column < ? OR column > ?", array(1, 2))
            $column = explode(" ", trim($condition))[0];
            if ($args != 2 || !is_array($parameters)) { // where("column < ? OR column > ?", 1, 2)
                $parameters = func_get_args();
                array_shift($parameters);
            }
        } else if (!is_array($parameters)) {//where(colum,value) => colum=value
            $condition .= " = ?";
            $parameters = [$parameters];
        } else if (is_array($parameters)) { // where("column", array(1, 2)) => column IN (?,?)
            $placeholders = $this->makePlaceholders(count($parameters));
            $condition = "({$condition} IN ({$placeholders}))";
        }

        $this->where_conditions[] = [
            "COLUMN"        => $column,
            "STATEMENT"     => $condition,
            "PARAMS"        => $parameters,
            "OPERATOR"      => $this->and_or_operator
        ];

        // Reset the where operator to AND. To use OR, you must call _or()
        $this->_and_();
        
        return $this;
    }

    /**
     * Create an AND operator in the where clause
     * 
     * @return Voodoo\VoodOrm 
     */
    public function _and_() 
    {
        if ($this->wrap_open) {
            $this->where_conditions[] = self::OPERATOR_AND;
            $this->last_wrap_position = count($this->where_conditions);
            $this->wrap_open = false;
        } else {
            $this->and_or_operator = self::OPERATOR_AND;
        }
        return $this;
    }

    
    /**
     * Create an OR operator in the where clause
     * 
     * @return Voodoo\VoodOrm 
     */    
    public function _or_() 
    {
        if ($this->wrap_open) {
            $this->where_conditions[] = self::OPERATOR_OR;
            $this->last_wrap_position = count($this->where_conditions);
            $this->wrap_open = false;
        } else {
            $this->and_or_operator = self::OPERATOR_OR;
        }
        return $this;
    }

    
    /**
     * To group multiple where clauses together.  
     * 
     * @return Voodoo\VoodOrm 
     */
    public function wrap()
    {
        $this->wrap_open = true;
        
        $spliced = array_splice($this->where_conditions, $this->last_wrap_position, count($this->where_conditions), "(");
        $this->where_conditions = array_merge($this->where_conditions, $spliced);

        array_push($this->where_conditions,")");
        $this->last_wrap_position = count($this->where_conditions);

        return $this;
    }
    
    /**
     * Where Primary key
     *
     * @param  int  $id
     * @return type
     */
    public function wherePK($id)
    {
        return $this->where($this->getPrimaryKeyname(), $id);
    }

    /**
     * WHERE $columName != $value
     *
     * @param  string   $columnName
     * @param  mixed    $value
     * @return Voodoo\VoodOrm 
     */
    public function whereNot($columnName, $value)
    {
        return $this->where("$columnName != ?", $value);
    }

    /**
     * WHERE $columName LIKE $value
     *
     * @param  string   $columnName
     * @param  mixed    $value
     * @return Voodoo\VoodOrm 
     */
    public function whereLike($columnName, $value)
    {
        return $this->where("$columnName LIKE ?", $value);
    }

    /**
     * WHERE $columName NOT LIKE $value
     *
     * @param  string   $columnName
     * @param  mixed    $value
     * @return Voodoo\VoodOrm 
     */
    public function whereNotLike($columnName, $value)
    {
        return $this->where("$columnName NOT LIKE ?", $value);
    }

    /**
     * WHERE $columName > $value
     *
     * @param  string   $columnName
     * @param  mixed    $value
     * @return Voodoo\VoodOrm 
     */
    public function whereGt($columnName, $value)
    {
        return $this->where("$columnName > ?", $value);
    }

    /**
     * WHERE $columName >= $value
     *
     * @param  string   $columnName
     * @param  mixed    $value
     * @return Voodoo\VoodOrm 
     */
    public function whereGte($columnName, $value)
    {
        return $this->where("$columnName >= ?", $value);
    }

    /**
     * WHERE $columName < $value
     *
     * @param  string   $columnName
     * @param  mixed    $value
     * @return Voodoo\VoodOrm 
     */
    public function whereLt($columnName, $value)
    {
        return $this->where("$columnName < ?", $value);
    }

    /**
     * WHERE $columName <= $value
     *
     * @param  string   $columnName
     * @param  mixed    $value
     * @return Voodoo\VoodOrm 
     */
    public function whereLte($columnName, $value)
    {
        return $this->where("$columnName <= ?", $value);
    }

    /**
     * WHERE $columName IN (?,?,?,...)
     *
     * @param  string   $columnName
     * @param  Array    $value
     * @return Voodoo\VoodOrm 
     */
    public function whereIn($columnName, Array $values)
    {
        return $this->where($columnName,$values);
    }
    
    /**
     * WHERE $columName NOT IN (?,?,?,...)
     *
     * @param  string   $columnName
     * @param  Array    $value
     * @return Voodoo\VoodOrm 
     */
    public function whereNotIn($columnName, Array $values)
    {
        $placeholders = $this->makePlaceholders(count($values));

        return $this->where("({$columnName} NOT IN ({$placeholders}))", $values);
    }

    /**
     * WHERE $columName IS NULL
     *
     * @param  string   $columnName
     * @return Voodoo\VoodOrm 
     */
    public function whereNull($columnName)
    {
        return $this->where("({$columnName} IS NULL)");
    }

    /**
     * WHERE $columName IS NOT NULL
     *
     * @param  string   $columnName
     * @return Voodoo\VoodOrm 
     */
    public function whereNotNull($columnName)
    {
        return $this->where("({$columnName} IS NOT NULL)");
    }

    
    public function having($statement, $operator = self::OPERATOR_AND) 
    {
        $this->is_fluent_query = true;
        $this->having[] = [
            "STATEMENT"   => $statement,
            "OPERATOR"    => $operator
        ];
        return $this;        
    }
    
    /**
     * ORDER BY $columnName (ASC | DESC)
     *
     * @param  string   $columnName - The name of the colum or an expression
     * @param  string   $ordering   (DESC | ASC)
     * @return Voodoo\VoodOrm 
     */
    public function orderBy($columnName, $ordering = "")
    {
        $this->is_fluent_query = true;
        $this->order_by[] = "{$columnName} {$ordering}";
        return $this;
    }

    /**
     * GROUP BY $columnName
     *
     * @param  string   $columnName
     * @return Voodoo\VoodOrm 
     */
    public function groupBy($columnName)
    {
        $this->is_fluent_query = true;
        $this->group_by[] = $columnName;
        return $this;
    }

    /**
     * LIMIT $limit
     *
     * @param  int      $offset
     * @return Voodoo\VoodOrm | int
     */
    public function limit($limit = null)
    {
        if ($limit) {
            $this->is_fluent_query = true;
            $this->limit = $limit; 
            return $this;
        } else {
            return $this->limit;
        }
    }
    
    /**
     * OFFSET $offset
     *
     * @param  int      $offset
     * @return Voodoo\VoodOrm | int
     */
    public function offset($offset = null)
    {
        if ($offset) {
            $this->is_fluent_query = true;
            $this->offset = $offset;
            return $this;
        } else {
            return $this->offset;
        }
    }
    
/*------------------------------------------------------------------------------
                                JOIN
*-----------------------------------------------------------------------------*/
    
    /**
     * Build a join
     *
     * @param  string   $table         - The table name
     * @param  string   $constraint    -> id = profile.user_id
     * @param  string   $table_alias   - The alias of the table name
     * @param  string   $join_operator - LEFT | INNER | etc...
     * @return Voodoo\VoodOrm 
     */
    public function join($tableName, $constraint, $tableAlias = "", $join_operator = self::JOIN_LEFT)
    {
        $this->is_fluent_query = true;
        $join = trim("{$join_operator} JOIN");
        $join .= self::EOL_TAB;
        $join .= " {$tableName} " . ($tableAlias ? "AS {$tableAlias} " : "");
        $join .= self::EOL_TAB . self::TAB;
        $join .= "ON ({$constraint})";
        $join .= self::EOL;
        $this->join_sources[] = $join;
        return $this;
    }
    
    /**
     * An alias to join by using a VoodOrm instance.
     * The VoodOrm instance may have select and where statement for the ON clause
     * 
     * @param \Voodoo\VoodOrm $voodorm
     * @param string $join_operator
     * @return Voodoo\VoodOrm
     */
    public function on(VoodOrm $voodorm, $join_operator = self::JOIN_LEFT)
    {
        $this->join_on = true;
        $constraint = str_replace("%join.", $this->getTableAlias().".", $voodorm->getJoinOnString());

        $this->select(array_map(function($row){
                            return str_replace("%join.", $this->getTableAlias()."." , $row);
                        }, $voodorm->getSelectFields())
                    );
        
        return $this->join(
                $voodorm->getTablename(), 
                $constraint, 
                $voodorm->getTableAlias(),
                $join_operator
            );
    }

/*------------------------------------------------------------------------------
                                Utils
*-----------------------------------------------------------------------------*/

    /**
     * Return the buit select query
     *
     * @return string
     */
    public function getSelectQuery()
    {
        $query = [
            "SELECT", self::EOL_TAB, $this->getSelectString(), 
            self::EOL,
            "FROM", self::EOL_TAB,  $this->getTableName(), "AS", $this->getTableAlias(),
            self::EOL,
            $this->getJoinString(),
            self::EOL,
            "WHERE", self::EOL_TAB, $this->getWhereString(),self::EOL
        ];
        
        if (! count($this->group_by) && $this->join_on) {
            $this->groupBy("%this.".$this->getPrimaryKeyname());
        }
        if (count($this->group_by)) {
            $query[] = "GROUP BY";
            $query[] = self::EOL_TAB;
            $query[] = $this->getGroupbyString();
            $query[] = self::EOL;
        }
        if (count($this->order_by)) {
            $query[] = "ORDER BY";
            $query[] = self::EOL_TAB;
            $query[] = $this->getOrderbyString();
            $query[] = self::EOL;
        }
        if (count($this->having)) {
            $query[] = "HAVING";
            $query[] = self::EOL_TAB;
            $query[] = $this->getHavingString();
            $query[] = self::EOL;
        }
        if ($this->limit) {
            $query[] = "LIMIT";
            $query[] = self::EOL_TAB;
            $query[] = $this->limit;
            $query[] = self::EOL;
        }
        if ($this->offset) {
            $query[] = "OFFSET";
            $query[] = self::EOL_TAB;
            $query[] = $this->offset;
            $query[] = self::EOL;
        }
        return $this->formatColumnName(implode(" ", $query));
    }

    /**
     * Get the select fields as string for SQL
     * 
     * @return string
     */
    public function getSelectString()
    {
        return implode(", " . self::EOL_TAB, $this->getSelectFields());
    }
    
    /**
     * Return the select fields as array
     * 
     * @return Array
     */
    public function getSelectFields()
    {
        if (!count($this->select_fields)) {
            $this->select("*");
        }
        return $this->prepareColumns($this->select_fields) ;       
    }
    
    /**
     * Get a JOIN string
     * 
     * @return string
     */
    public function getJoinString()
    {
        return (" ").implode(" ",$this->join_sources);
    }
    
    /**
     * Get the group by string
     * 
     * @return string
     */
    public function getGroupbyString()
    {
        return implode(", ", array_unique($this->group_by));
    }
    
    /**
     * Get the order by string
     * 
     * @return string
     */
    public function getOrderbyString()
    {
        return implode(", ", array_unique($this->order_by));
    }
    
    /**
     * Build the WHERE clause(s)
     *
     * @return string
     */
    public function getWhereString()
    {
        // If there are no WHERE clauses, return empty string
        if (!count($this->where_conditions)) {
            return "1";
        } 

        $where_condition = "";
        $last_condition = "";

        foreach ($this->where_conditions as $condition) {
            if (is_array($condition)) {
                if ($where_condition && $last_condition != "(" && !preg_match("/\)\s+(OR|AND)\s+$/i", $where_condition)) {
                    $where_condition .= $condition["OPERATOR"];
                }
                $where_condition .= $condition["STATEMENT"];
                $this->where_parameters = array_merge($this->where_parameters, $condition["PARAMS"]);
            } else {
                $where_condition .= $condition;
            }
            $last_condition = $condition;
        }
 
        $columns = [];
        foreach($this->where_conditions as $condition) {
            $column = $condition["COLUMN"];
            $columns[$column] = (strpos($column, ".") === false) ? "%this.{$column}" : $column;
        }
        $stmt = str_replace(array_keys($columns), array_values($columns), $where_condition);
        return $this->formatColumnName($stmt);
    }
    
    /**
     * Create the JOIN ... ON string when there is a join. It will be called by on()  
     *
     * @return string
     */
    public function getJoinOnString()
    {
        $where = $this->getWhereString();
        
        $params = $this->where_parameters;
        return preg_replace_callback('/\?/', function($match) use(&$params) 
                {
                    $arg = array_shift($params);
                    if (is_numeric($arg)) {
                        return $arg;
                    } else if (strpos($arg, "%") !== FALSE) {
                        return $arg;
                    } else {
                        return "'{$arg}'";
                    }
                }, 
                $where);
    }
    
    /**
     * Return the HAVING clause
     * 
     * @return string
     */
    protected function getHavingString()
    {
        // If there are no WHERE clauses, return empty string
        if (!count($this->having)) {
            return "";
        } 

        $having_condition = "";

        foreach ($this->having as $condition) {
            if (is_array($condition)) {
                if ($having_condition && !preg_match("/\)\s+(OR|AND)\s+$/i", $having_condition)) {
                    $having_condition .= $condition["OPERATOR"];
                }
                $having_condition .= $condition["STATEMENT"];
            } else {
                $having_condition .= $condition;
            }
        }
        return $having_condition;        
    }

    /**
     * Return the values to be bound for where
     *
     * @return Array
     */
    protected function getWhereParameters()
    {
        return $this->where_parameters;
    }

    /**
      * Detect if its a single row instance and reset it to PK
      *
      * @return Voodoo\VoodOrm 
      */
    protected function setSingleWhere()
    {
        if ($this->is_single) {
            $this->resetWhere();
            $this->wherePK($this->getPK());
        }
        return $this;
    }

    /**
      * Reset the where
      *
      * @return Voodoo\VoodOrm 
      */
    protected function resetWhere()
    {
        $this->where_conditions = [];
        $this->where_parameters = [];
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
     * @return Voodoo\VoodOrm 
     */
    public function insert(Array $data)
    {
        $insert_values = [];
        $question_marks = [];

        // check if the data is multi dimention for bulk insert
        $multi = $this->isArrayMultiDim($data);

        $datafield = array_keys( $multi ? $data[0] : $data);

        if ($multi) {
            foreach ($data as $d) {
                $question_marks[] = '('  . $this->makePlaceholders(count($d)) . ')';
                $insert_values = array_merge($insert_values, array_values($d));
            }
        } else {
            $question_marks[] = '('  . $this->makePlaceholders(count($data)) . ')';
            $insert_values = array_values($data);
        }

        $sql = "INSERT INTO {$this->table_name} (" . implode(",", $datafield ) . ") ";
        $sql .= "VALUES " . implode(',', $question_marks);

        $this->query($sql,$insert_values);

        // Return the SQL Query
        if ($this->debug_sql_query) {
            $this->debugSqlQuery(false);
            return $this;
        }
                
        $rowCount = $this->rowCount();

        // On single element return the object
        if ($rowCount == 1) {
            $primaryKeyname = $this->getPrimaryKeyname();
            $data[$primaryKeyname] = $this->pdo->lastInsertId($primaryKeyname);
            return $this->fromArray($data);
        }

        return $rowCount;
    }

/*------------------------------------------------------------------------------
                                Updating
*-----------------------------------------------------------------------------*/    
    /**
      * Update entries
      * Use the query builder to create the where clause
      *
      * @param Array the data to update
      * @return int - total affected rows
      */
    public function update(Array $data = null)
    {
        $this->setSingleWhere();

        if (! is_null($data)) {
            $this->set($data);
        }

        // Make sure we remove the primary key
        unset($this->_dirty_fields[$this->getPrimaryKeyname()]);
        
        $values = array_values($this->_dirty_fields);
        $field_list = [];

        if (count($values) == 0){
            return false;
        }

        foreach (array_keys($this->_dirty_fields) as $key) {
            $field_list[] = "{$key} = ?";
        }

        $query = [
            "UPDATE", self::EOL_TAB, $this->getTablename(), "AS", $this->getTableAlias(), self::EOL,
            "SET", self::EOL_TAB, implode(", ",$field_list), self::EOL,
            "WHERE", self::EOL_TAB, $this->getWhereString(), self::EOL
        ];
        $this->query(implode(" ", $query), array_merge($values, $this->getWhereParameters()));
        
        // Return the SQL Query
        if ($this->debug_sql_query) {
            $this->debugSqlQuery(false);
            return $this;
        } else {
            $this->_dirty_fields = [];
            return $this->rowCount();            
        }
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
        
        if (count($this->where_conditions)) {
            
            $query = [
                "DELETE FROM", self::EOL_TAB, $this->getTablename(), self::EOL,
                "WHERE", self::EOL_TAB, $this->getWhereString(), self::EOL
            ];
            $this->query(implode(" ", $query), $this->getWhereParameters());           
        } else {
            if ($deleteAll) {
                $query  = "DELETE FROM {$this->table_name}";
                $this->query($query);                
            } else {
                return false;
            }
        }

        // Return the SQL Query
        if ($this->debug_sql_query) {
            $this->debugSqlQuery(false);
            return $this;
        } else {
           return $this->rowCount(); 
        }
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
     * @return Voodoo\VoodOrm 
     */
    public function set($key, $value = null)
    {
        if(is_array($key)) {
            foreach ($key as $keyKey => $keyValue) {
                $this->set($keyKey, $keyValue);
            }
        }  else {
            if( $key != $this->getPrimaryKeyname()) {
                $this->_data[$key] = $value;
                $this->_dirty_fields[$key] = $value;                
            }
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
        if ($this->is_single || count($this->where_conditions)) {
            return $this->update();
        } else {
            return $this->insert($this->_dirty_fields);
        }
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
    public function count($column = null)
    {
        if (! $column) {
            $column = $this->getPrimaryKeyname();
        }
        return $this->aggregate("COUNT({$this->prepareColumn($column)})");
    }

    /**
     * Return the aggregate max count of column
     *
     * @param  string $column - the column name
     * @return double
     */
    public function max($column)
    {
        return $this->aggregate("MAX({$this->prepareColumn($column)})");
    }


    /**
     * Return the aggregate min count of column
     *
     * @param  string $column - the column name
     * @return double
     */
    public function min($column)
    {
        return $this->aggregate("MIN({$this->prepareColumn($column)})");
    }

    /**
     * Return the aggregate sum count of column
     *
     * @param  string $column - the column name
     * @return double
     */
    public function sum($column)
    {
        return $this->aggregate("SUM({$this->prepareColumn($column)})");
    }

    /**
     * Return the aggregate average count of column
     *
     * @param  string $column - the column name
     * @return double
     */
    public function avg($column)
    {
        return $this->aggregate("AVG({$this->prepareColumn($column)})");
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
/*------------------------------------------------------------------------------
                                Association
*-----------------------------------------------------------------------------*/
    
    /**
     * Association / Load
     * 
     * __call() will load a table by association or return the table object itself
     * 
     * To dynamically call a table
     *
     * $VoodOrm = new VoodOrm($myPDO);
     * on table 'users'
     * $Users = $VoodOrm->table("users");
     *
     * Or to call a table association
     * on table 'photos' where users can have many photos
     * $allMyPhotos = $Users->findOne(1234)->photos();
     * 
     * Association allow you to associate the current table with another by using
     * foreignKey and localKey. The data is eagerly loaded hence only making one round to the table 
     * to retrieve the data matching the foreign and private keys
     * foreign and private keys are cached for subsequent queries, 
     * the keys are selected based on the foreignKeyname pattern. 
     * i.e: having the keys: id, user_id, friend_id, name, last_name
     * id, user_id, friend_id will be cached so they can be queried upon request
     * 
     * @param  string $tablename
     * 
     * @param  Array $args
     *      association
     *      foreignKey
     *      localKey
     *      where
     *      sort
     *      callback
     *      model
     *      backref
     * @return SplFixedArray | Object | Null
     */
    public function __call($tablename, $args)
    {
        $_def = [
            "association"   => self::ASSO_MANY, // The type of association: MANY | ONE
            "model"         => null,  // An instance VoodOrm class as the class to interact with
            "foreignKey"    => "", // the foreign key for the association
            "localKey"      => "", // localKey for the association
            "columns"       => "*", // the columns to select
            "where"         => [], // Where condition
            "sort"          => "", // Sort of the result
            "callback"      => null, // A callback on the results
            "backref"       => false // When true, it will query in the reverse direction
        ];
        $prop = array_merge($_def, $args);

        if ($this->is_single) {
            switch ($prop["association"]) {
                /**
                 * OneToMany
                 */
                default:
                case self::ASSO_MANY:
                    $localKeyN = ($prop["localKey"]) ?: $this->getPrimaryKeyname();
                    $foreignKeyN = ($prop["foreignKey"]) ?: $this->getForeignKeyname();
                    
                    $token = $this->tokenize($tablename, $foreignKeyN . ":" . $prop["association"]);

                    if (!isset(self::$references[$token])) {
                        
                        $model = $prop["model"] ?: $this->table($tablename);

                        // Backref (back reference). Reverse the query
                        if ($prop["backref"]) {
                            if (isset($this->reference_keys[$foreignKeyN])) {
                                $model->where($localKeyN, $this->reference_keys[$foreignKeyN]);
                            }
                        } else {
                            if (isset($this->reference_keys[$localKeyN])) {
                                $model->where($foreignKeyN, $this->reference_keys[$localKeyN]); 
                            }
                        }
                        if($prop["where"]) {
                            $model->where($prop["where"]);
                        }
                        if ($prop["sort"]) {
                            $model->orderBy($prop["sort"]);
                        }
                        if ($prop["columns"]) {
                            $model->select($prop["columns"]);
                        }
                        self::$references[$token] = $model->find(function($rows) use ($model, $foreignKeyN, $prop) {
                            $results = [];
                            $tmp = [];
                            foreach ($rows as $row) {
                                $_i = $row[$foreignKeyN];
                                if (! isset($tmp[$_i])) {
                                    $tmp[$_i] = [];
                                }
                                $tmp[$_i][] = is_callable($prop["callback"]) ? $prop["callback"]($row) : $model->fromArray($row);
                            }
                            foreach ($tmp as $index => $data) {
                                $results[$index] = SplFixedArray::fromArray($data);
                                unset($tmp[$index]);
                            }
                            unset($tmp);
                            return $results;
                        });
                    }
                    return isset(self::$references[$token][$this->{$localKeyN}])
                                ? self::$references[$token][$this->{$localKeyN}] 
                                : new ArrayIterator;
                    break;

                case self::ASSO_ONE:
                    $localKeyN = $prop["localKey"] ?: $this->formatKeyname($this->getStructure()["foreignKeyname"], $tablename);
                    
                    if (isset($this->{$localKeyN}) && $this->{$localKeyN}) {
                        $model = $prop["model"] ?: $this->table($tablename);
                        $foreignKeyN = $prop["foreignKey"] ?: $model->getPrimaryKeyname();
                        
                        $token = $this->tokenize($tablename, $localKeyN . ":" . $prop["association"]);

                        if (! isset(self::$references[$token])) {
                            if (isset($this->reference_keys[$localKeyN])) {
                               $model->where($foreignKeyN, $this->reference_keys[$localKeyN]); 
                            }    
                            
                            if ($prop["columns"]) {
                                $model->select($prop["columns"]);
                            }
                            
                            self::$references[$token] = $model->find(function($rows) use ($model, $callback, $foreignKeyN) {
                               $results = [];
                               foreach ($rows as $row) {
                                    $results[$row[$foreignKeyN]] = is_callable($callback)
                                                                    ? $callback($row)
                                                                    : $model->fromArray($row);
                               }
                               return $results;
                            });
                        }
                        return self::$references[$token][$this->{$localKeyN}];
                    } else {
                        return null;
                    }
                    break;
            }
        } else {
            return $prop["model"] ?: $this->table($tablename);
        }

    }

/*******************************************************************************/
// Utilities methods

    /**
     * Reset fields
     *
     * @return Voodoo\VoodOrm 
     */
    public function reset()
    {
        $this->where_parameters = [];
        $this->select_fields = [];
        $this->join_sources = [];
        $this->where_conditions = [];
        $this->limit = null;
        $this->offset = null;
        $this->order_by = [];
        $this->group_by = [];
        $this->_data = [];
        $this->_dirty_fields = [];
        $this->is_fluent_query = true;
        $this->and_or_operator = self::OPERATOR_AND;
        $this->having = [];
        $this->wrap_open = false;
        $this->last_wrap_position = 0;
        $this->debug_sql_query = false;
        $this->pdo_stmt = null;
        $this->is_single = false;
        $this->join_on = false;
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
    public static function NOW($datetime = "now")
    {
        return (new DateTime($datetime ?: "now"))->format("Y-m-d H:i:s");
    }


/*******************************************************************************/
// Query Debugger
    
    /**
     * To debug the query. It will not execute it but instead using debugSqlQuery()
     * and getSqlParameters to get the data
     * 
     * @param bool $bool
     * @return Voodoo\VoodOrm 
     */
    public function debugSqlQuery($bool = true)
    {
        $this->debug_sql_query = $bool;
        return $this;
    }
    
    /**
     * Get the SQL Query with 
     * 
     * @return string 
     */
    public function getSqlQuery()
    {
        return $this->sql_query;
    }
    
    /**
     * Return the parameters of the SQL
     * 
     * @return array
     */
    public function getSqlParameters()
    {
        return $this->sql_parameters;
    }
    
/*******************************************************************************/
    public function __clone()
    {}
    
    public function __toString()
    {
        return $this->is_single ? $this->getPK() : $this->table_name;
    } 
    
    /**
     * Return a string containing the given number of question marks,
     * separated by commas. Eg "?, ?, ?"
     *
     * @param int - total of placeholder to inser
     * @return string
     */
    protected function makePlaceholders($number_of_placeholders=1)
    {
        return implode(", ", array_fill(0, $number_of_placeholders, "?"));
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
       return sprintf($pattern, $tablename);
    }

    /**
     * To create a string that will be used as key for the relationship
     *
     * @param  type   $key
     * @param  type   $suffix
     * @return string
     */
    private function tokenize($key, $suffix = "")
    {
        return  $this->table_token.":$key:$suffix";
    }   
    
    /**
     * Check if aray is multi dim
     * @param array $data
     * @return bool
     */
    private function isArrayMultiDim(Array $data)
    {
        return (count($data) != count($data,COUNT_RECURSIVE));
    }
    
    /**
     * Prepare columns to include the table alias name
     * @param array $columns
     * @return array
     */
    private function prepareColumns(Array $columns)
    {
        $newColumns = [];
        foreach ($columns as $column) {
            if (strpos($column, ",")) {
                $newColumns = array_merge($this->prepareColumns(explode(",", $column)), $newColumns);
            } else {
                $newColumns[] = $this->prepareColumn($column);
            }
        }
        return $newColumns;
    }
    
    private function prepareColumn($column)
    {
        $column = trim($column);
        if (strpos($column, ".") === false && strpos(strtoupper($column), "NULL") === false) {
            if (! preg_match("/^[0-9]/", $column)) {
                $column = "%this.{$column}";
            }
        }
        return $this->formatColumnName($column);
    }
    /**
     * Format a column name to add the the table alias
     * 
     * @param string $column
     * @return string
     */
    public function formatColumnName($column)
    {
        return str_replace("%this.", $this->getTableAlias().".", $column);
    }
}
