
# VoodOrm 2.x.x

#### A simple micro-ORM that stays out of your way

---
 
Name: [VoodOrm](http://github.com/mardix/VoodOrm)

License: MIT

Author: [Mardix](http://github.com/mardix)

Version : 2.x.x

Requirements: PHP >= 5.4, PDO

---

## About Voodoo!

VoodOrm is a micro-ORM which functions as both a fluent select query API and a CRUD model class.

VoodOrm is built on top of PDO and is well fit for small to mid-sized projects, where the emphasis 
is on simplicity and rapid development rather than infinite flexibility and features.
VoodOrm works easily with table relationship. And offers api that gets SQL out of your way

--- 

## Features

- PDO and prepared statements
- Fluent Query
- Relationship
- Joins
- Aggregation
- Query debugger and query profiler
- Active Record pattern

## Requirements

- PHP >= 5.4
- PDO

## Error Reporting
VoodOrm does not escalate errors. Non-existing table produces SQL error that is reported by PDO conforming to PDO::ATTR\_ERRMODE. Non-existing columns produces the same E_NOTICE as an attempt to access non-existing key in array.

## What it doesn't do. 

We believe it's best if certain stuff is kept to the developer to do, like caching or data validation. Also data validation can be done at the database level.

- No models or entities generation
- No data validation
- No caching
- No database migration

## Install VoodOrm

You can just download VoodOrm as is, or with Composer. 

To install with composer, add the following in the require key in your **composer.json** file

	"voodoophp/voodorm": "2.*"

composer.json

	{
	    "name": "voodoophp/myapp",
	    "description": "My awesome Voodoo App",
	    "require": {
	        "voodoophp/voodorm": "2.*"
	    }
	}

---
## Working with VoodOrm

---
### ***new VoodOrm(*** *PDO $pdo* ***)***

To get started with VoodOrm, you have to setup the PDO connection. We'll be using the variable $DB as the database connection, `$users` as the table, `$friends` as another table throughout this whole tutorial

	$pdo = new PDO("mysql:host=localhost;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$DB = new VoodOrm($pdo);

---

### *VoodOrm* ***VoodOrm::table(*** *string $tablename* ***)*** 
To connect to a table is straight forward by calling the method `VoodOrm::table()` 

	$users = $DB->table('users');

You can also set the table by calling the table as a method. The above can be also written like this
	
	$users = $DB->users();
	$friends = $DB->friends();


From there you will be able able to do any CRUD on the table with VoodOrm fluent query interface

---
##Data Modification

VoodOrm supports data modification (insert, update and delete). No data validation is performed by VoodOrm but all database errors are reported by the standard PDO error reporting. For data validation, we believe it's best to validate your data at the database level or application level

---

### *mixed* ***VoodOrm::insert(*** *Array $data* ***)***
To insert data in the table, use the `insert(Array $data)` where `$data` can be a one dimentional array to insert  just one entry, or a muliple arrays to do mass insert. 

If a single row was inserted, it will return the active record of the created object. Otherwise it will return the total entries inserted

For a single entry:

	$user = $users->insert(array(
		 			"name" => "Mardix",
					"age" => 30, 
		 			"city" => "Charlotte",
	     			"state" => "NC",
					"device" => "mobile",
		 			"timestamp" => $DB->NOW()
				));

Returns the VoodOrm active record instance of this entry where you can use
`$user->name` or `$user->city`. We'll be able to do more later.

For a mass insert:

	$massInserts = $users->insert(array(
						array(
							 "name" => "Mardix",
							 "city" => "Charlotte",
						     "state" => "NC",
						     "device" => "mobile",
							 "timestamp" => $DB->NOW()
						),
						array(
							 "name" => "Cesar",
							 "city" => "Atlanta",
						     "state" => "GA",
							 "device" => "mobile",
							 "timestamp" => $DB->NOW()
						),
						array(
							 "name" => "Gaga",
							 "city" => "Port-au-Prince",
						     "state" => "HT",
							 "device" => "computer",
							 "timestamp" => $DB->NOW()
						),
					));


returns the total entries that were inserted

---

### *mixed* ***VoodOrm::update(Array $data)***
 
There are two ways to update entries in VoodOrm, 1) by using the active record pattern for a fetched row, or 2) by using a where query to specify where to update. Also the method `VoodOrm::set($key, $value)` can be use to set the data before updating.

***For single entry***

	$user->update(array(
						"city" => "Raleigh"
					));

it's the same as

	$user->city = "Raleigh";
	$user->update();

You can also use `save()` instead of `update()`

	$user->save();


or with *Voodoo::set(Array $data) or Voodoo::set($key, $value)*

	$user->set('city','Raleigh')->update();

***For multiple entries:***

For multiple entries we'll use `VoodOrm::set()` and `VoodOrm::where()` to specify where to update.

*Voodoo::set(Array $data) or Voodoo::set($key, $value)*

For mass update, we'll set the data to update using `set(Array $data)` and `where($k, $v)`

	$user->set(array(
					"country_code" => "US"
			))
			->where("device", "mobile")
			->update();

**There are more fluent `where` aliases under Fluent Query Interface*

---

### *mixed* ***VoodOrm::save()***
`Save()` is a shortcut to `VoodOrm::insert()` or `VoodOrm::update()`

To insert new data:

	$user = $DB->users();
	$user->name = "Mardix";
	$user->city = "Charlotte";
	$user->save(); 

To update:

	$user = $users->findOne(123456);
	$user->city = "Atlanta";
	$user->save();

---

### *int* ***VoodOrm::delete()***
To delete entries we'll use the `VoodOrm::delete()` method

For single entries, by invoking the `delete()` method it will delete the current entry

	$user = $users->reset()->findOne(1234);
	$user->delete();

For multiple entries, we will use the `VoodOrm::where()` method to specify where to delete

	$users->where("city", "Charlotte")->delete();

---

## Aggregation

VoodOrm gives you access to aggregation methods on your table

---

#### *int* ***VoodOrm::count()***
To count all the entries based on where clause

	$allUsers = $users->count();

	$count = $voodorm->where($x, $y)->count();

or for a specific column name

	$count = $users->where($x, $y)->count($columnName);


#### *float* ***VoodOrm::max(*** *string $columnName* ***)***
To get the max of a $columnName based on where() clause

	$max = $users->where($x, $y)->max($columnName);


#### *float* ***VoodOrm::min(*** *string $columnName* ***)***
To get the min of a $columnName based on where() clause

	$min = $users->where($x, $y)->min($columnName);


#### *float* ***VoodOrm::sum(*** *string $columnName* ***)***
To get the sum of a $columnName based on where() clause

	$sum = $users->where($x, $y)->sum($columnName);


#### *float* ***VoodOrm::avg(*** *string $columnName* ***)***
To get the average of a $columnName based on where() clause

	$avg = $users->where($x, $y)->avg($columnName);

#### *mixed* ***VoodOrm::aggregate(*** *string $function* ***)***
To run any aggregation function

	$agg = $users->where($x, $y)->aggregate('GROUP_CONCAT $columnName');

---

## Querying
VoodOrm provides a fluent interface to enable simple queries to be built without writing a single character of SQL. 

Tow methods allow you to get a single entry or multiple entries: `findOne()` and `find()`. And `fromArray()` that load a raw data to the object. 

---
## FindOne

### *VoodOrm* ***VoodOrm::findOne()***

`findOne()` returns `VoodOrm` instance of a single entry if found, otherwise it will return `FALSE`.


	$user = $users->where('id', 1234)
				  ->findOne();

The primary key can be set in the `findOne(int $primaryKey)` to get the same result as the above query. Meaning no need to have a `where()` clause.

 	$user = $users->findOne(1234);

Let's get the entry found:

	if ($user) {
		echo " Hello $user->name!";

	// On a retrieved entry you can perform update and delete
		$user->last_viewed = $users->NOW();
		$suer->save();
	}

---

## Find

### *ArrayIterator* ***VoodOrm::find()***
`find()` returns an `ArrayIterator` of the rows found which are instances of `VoodOrm`, otherwise it will return `False`. 

	$allUsers = $users->where('gender', 'male')
					  ->find();

	foreach ($allUsers as $user) {
		echo "{$user->name}";

	// On a retrieved entry you can perform update and delete
		$user->last_viewed = $users->NOW();
		$user->save();
	}

`find()` also contains a shortcut when it's called in an iteration such as foreach:

	$allUsers = $users->where('gender', 'male');

	foreach ($allUsers as $user) {
		echo "{$user->name}";

	// On a retrieved entry you can perform update and delete
		$user->last_viewed = $users->NOW();
		$suer->save();
	}
					  

### *mixed* ***VoodOrm::find(*** *Closure $callback* ***)***

`VoodOrm::find()` also accept a Closure as a callback to do your own data manipulation. Upon execution, VoodOrm will pass the data found from the query to the closure function.

		$users->where('gender', 'male');

		$results = $users->find(function($data){
			$newResults = array();

			foreach ($data as $d) {
				$d["full_name"] = ucwords("{$data["first_name"]} {$data["last_name"]}");
 				$newResults[] = $d;
			}

			return $newResults;
		});	

---

## FromArray

### *VoodOrm* ***VoodOrm::fromArray(*** *Array $data* ***)***

Unlike `find()` and `findOne()` which make a query to the database to retrieve the data, `fromArray()` loads raw data and returns it as a VoodOrm object. It can be data that is cached into Redis/Memcached, but not coming directly from the database.

	$data = [
			"id" => 916,
			"name" => "Jose",
			"last_name" => "Martinez"
			];

	$anotherUser = $users->fromArray($data);

Now you can operate on it

	$anotherUse->update(
						["name" => "Yolo"]
					);

---	

## Fluent Query Builder

---



## Select

### *VoodOrm* ***VoodOrm::select(*** *$columns = '\*'*  ***)***
To select the fields in the table. If ommitted, VoodOrm will fetch all the columns.

	$users->select()

or with selected columns

	$users->select("name, age")
			->select("last_viewed");

	> SELECT name, age, last_viewed

---

## Where

`Where` allow you to set where clauses for the query. Below you will find many aliases for `where`

`Where` clauses work with VoodOrm::find()` , `VoodOrm::findOne()`, `VoodOrm::update()` and `VoodOrm::delete()`

Repetitive call of `where` or any `where` aliases will append the where clause to the previous where by using the AND operator. To use the OR operator instead you must call to `VoodOrm::_or()`. More below.


### *VoodOrm* ***VoodOrm::where(*** *$condition $parameters = array()*  ***)***

This is the main `where`. It is responsible for all the wheres.

`$condition` is the condition to use. It can contain ? or :name which is bound by PDO to `$parameters` (so no manual escaping is required).


`$parameters` is the value(s) to bind to the condition. It can be one array, one associative array or zero or more scalars. 

Som examples

	$users->where("name", "Mardix");
	WHERE name = ?

	$users->where("age > ?", 25);
	WHERE age > ?

	$users->where("name in (?, ?, ?)", "Mike", "Jones", "Rich");
	WHERE name IN (?, ?, ?)

	$users->where("(field1, field2)", array(array(1, 2), array(3, 4)))
	WHERE (field1, field2) IN ((?, ?), (?, ?))

But to facilitate the task, VoodOrm comes with some aliases for common operation:
### *VoodOrm* ***VoodOrm::wherePK(*** *int $primaryKey*  ***)***
Where the primary key is set
	$users->wherePK(1234);

### *VoodOrm* ***VoodOrm::whereNot(*** *$columnName, $value*  ***)***

	$users->whereNot('age', 24);

	WHERE age != ?

	
### *VoodOrm* ***VoodOrm::whereLike(*** *$columnName, $value*  ***)***

	$users->whereLike('name', 'w%');

	WHERE name LIKE ?

### *VoodOrm* ***VoodOrm::whereNotLike(*** *$columnName, $value*  ***)***
	$users->whereNotLike('name', 'r%');

	WHERE name NOT LIKE ?

### *VoodOrm* ***VoodOrm::whereGt(*** *$columnName, $value*  ***)***
	$users->whereGt('age', 21);

	WHERE age > ?

### *VoodOrm* ***VoodOrm::whereGte(*** *$columnName, $value*  ***)***
	$users->whereGte('age', 21);

	WHERE age >= ?

### *VoodOrm* ***VoodOrm::whereLt(*** *$columnName, $value*  ***)***
	$users->whereLt('age', 21);

	WHERE age < ?

### *VoodOrm* ***VoodOrm::whereLte(*** *$columnName, $value*  ***)***
	$users->whereLte('age', 21);

	WHERE age <= ?

### *VoodOrm* ***VoodOrm::whereIn(*** *$columnName, Array $value*  ***)***
	$users->whereIn('city', array('Charlotte', 'Atlanta'));

	WHERE city IN (?,?)

### *VoodOrm* ***VoodOrm::whereNotIn(*** *$columnName, Array $value*  ***)***
	$users->whereNotIn('city', array('Chicago', 'Miami'));

	WHERE city NOT IN (?,?)

### *VoodOrm* ***VoodOrm::whereNull(*** *$columnName*  ***)***
	$users->whereNull('city');

	WHERE city IS NULL

### *VoodOrm* ***VoodOrm::whereNotNull(*** *$columnName*  ***)***
	$users->whereNotNull('name');

	WHERE city NOT NULL

---

## Where with OR and AND
Building your query, you will want to add AND and OR operator in your where clause. To do so, use `VoodOrm::_and()` and `VoodOrm::_or()` chained to any `where` aliases
 
### *VoodOrm* ***VoodOrm::_and()***
To add the `AND` operator in a where query. `AND` is set by default, if not `_and()` is not called, VoodOrm will add it by default. 

	$users->where("city", "Charlotte")->_and()->whereGte("age", 21);

	WHERE city = ? AND age >= ?


### *VoodOrm* ***VoodOrm::_or()***
To add the `OR` operator in a where query.

	$users->where("city", "Charlotte")->_or()->whereGte("age", 21)->_or()->where("gender", "female");

	WHERE city = ? OR age >= ? OR gender = ?
	
---

## Where with Wrap()
When building quasi complicated query with multiple set of where, `VoodOrm::wrap()` group the where together in parenthesis. 

### *VoodOrm* ***VoodOrm::wrap()***

	$users->where("city", "Charlotte")->whereGte("age", 21)->wrap()
		  ->where("gender", "female")->where("city", "Atlanta");

	WHERE (city = ? AND age >= ?) AND (gender = ? AND city = ?)

### *VoodOrm* ***VoodOrm::wrap()->_and()***

`wrap()->_and()` does a union with another grouped where with the `AND` operator.

	$users->where("city", "Charlotte")->whereGte("age", 21)->wrap()->_and()
		  ->where("gender", "female")->where("city", "Atlanta");

	WHERE (city = ? AND age >= ?) AND (gender = ? AND city = ?)

### *VoodOrm* ***VoodOrm::wrap()->_or()***

`wrap()->_or()` does a union with another grouped where with the `OR` operator.

	$users->where("city", "Charlotte")->whereGte("age", 21)->wrap()->_or()
		  ->where("gender", "female")->where("city", "Atlanta");

	WHERE (city = ? AND age >= ?) OR (gender = ? AND city = ?)

#### wrap()->\_and() and wrap()->\_or() in the same query

	$users->where("id",1)->where("city","charlotte")->wrap()
	      ->where("gender","female")->where("country","US")->wrap()
	      ->_or()->where("city",array("Charlotte","Atlanta"))->wrap()
	      ->_or()->whereLt('age',21)->whereGte("name","Mardix")->wrap();
	
	WHERE (id = ? AND city = ?) 
		  AND (gender = ? AND country = ?) 
          OR ((city IN (?, ?))) 
	      OR (age < ? AND name >= ?) 

---

## Order, Group, Limit, Offset

### *VoodOrm* ***VoodOrm::orderBy(*** *$columnName, $ordering*  ***)***
	$users->orderBy('name', 'DESC');

	ORDER BY name DESC

### *VoodOrm* ***VoodOrm::groupBy(*** *$columnName*  ***)***
	$users->groupBy('city');

	GROUP BY city

### *VoodOrm* ***VoodOrm::limit(*** *int $limit*  ***)***
	$users->limit(10);

	LIMIT 10

### *VoodOrm* ***VoodOrm::offset(*** *int $offset*  ***)***
	$users->offset(10);

	OFFSET 10

---
## Joins
### *VoodOrm* ***VoodOrm::join(*** *$tablename, $constraint, $table_alias , $join_operator* ***)***
	$users->join('friends', 'f.user_id = u.id', 'f')

	JOIN friends AS f ON f.user_id = u.id

### *VoodOrm* ***VoodOrm::leftJoin(*** *$tablename, $constraint, $table_alias* ***)***
	$users->leftJoin('friends', 'f.user_id = u.id', 'f')

	LEFT JOIN friends AS f ON f.user_id = u.id

---

## Relationship
That's the killer!

One of VoodOrm killer feature is Relationship. By calling a table as a method on an object automatically creates a One To Many relationship on that reference table by default.

For this example we'll have two tables: `user` (id, name, dob) and `friend` (id, user\_id, friend\_id)

The `friend.user_id` is the foreign key to the `user` table. And `friend.friend_id` is the foreign key of the friend's `user.id`.

Let's get all the users and their friends

    $allUsers = $users->find();
	
    foreach ($allUsers as $user) {
        /**
        * Connect to the 'friend' table = $user->friend();
        * In the back, it does a ONE To MANY relationship 
        * SELECT * FROM friend WHERE friend.user_id = user.id 
        */
        $allFriends = $user->friend();
        foreach ($allFriends as $friend) {
            echo "{$friend->friend_id} : ";

            /**
            * We got the friend's entry, we want to go back in the user table
            * So we link back the friend table to the user table
            * SELECT * FROM user WHERE user.id = friend.friend_id LIMIT 1 
            * It will do a ONE to One relationship
            */
            echo $friend->user(Voodoo\VoodOrm::REL_HASONE, "friend_id")->name;

            echo "\n";
        }
    }
    
    // Same as above but with just one user
    $user = $users->findOne($userId);
    if($user) {

        foreach ($user->friend() as $friend) {

            echo "{$friend->friend_id} : ";

            echo $friend->user(Voodoo\VoodOrm::REL_HASONE, "friend_id")->name;

            echo "\n";
        }
    }




That's the big picture. Calling a reference table as a method on a object will do a relationship. 

## Relationship: *One to Many*

One to many relationship, in our user and friend case, it's where a user can have one or more friends. But each `friend.friend_id` is associated to one `user.id`. This type of relationship will return one or more entries. 

The relationship between `user` (single-valued) and `friend` (multi-valued) is a one-to-many relationship. 

In our above example, we did a One to Many relationship in the friend's table

	$allFriends = $user->friend();

Relationship Constants

VoodOrm has pre-defined constant that let you select execute a type of relationship

*CONST::REL\_HASMANY (2)*

	$allFriends = $user->friend(Voodoo\VoodOrm::REL_HASMANY);

This is faster. It does an eager loading by fetching all the data and hold the data in memory. It executes only one query. It is used by default.


*CONST::REL\_LAZYMANY (-2)*

	$allFriends = $user->friend(Voodoo\VoodOrm::REL_LAZYMANY);

This is slower. It does a lazy loading by fetching the data as it's being requested. It will execute 1+N queries.



## Relationship: *One to One*
One-to-one relationships are single-valued in both directions. In the friend's table, `friend.friend_id` is linked to `user.id`  

	$allFriends = $user->friend();

Relationship Constants

*CONST::REL\_HASONE (1)*

	$friendUser = $friend->user(Voodoo\Core\VoodOrm::REL_HASONE, "friend_id");
	echo $friendUser->name;

It does an eager loading by fetching all the data and hold the data in memory. It executes only one query. It is used by default.

*CONST::REL\_LAZYONE (-1)*

	$friendUser = $friend->user(Voodoo\VoodOrm::REL_LAZYONE, "friend_id");
	echo $friendUser->name;

This is slower. It does a lazy loading by fetching the data as it's being requested. It will execute 1+N queries.



#### Relationship: *Many to Many*
Not implemented. That shit is complicated.... can't find a one case fit all... 

## Relationship Parameters

VoodOrm relationship accept 4 types of parameters that can be placed anywhere:

	$user->friend(NUMBER, STRING, ARRAY, CALLBACK);

**NUMBER** : Usually that's the relationship constants `REL_HASONE = 1`, `REL_LAZYONE = -1`, `RE_HASMANY = 2`, `REL_LAZYMANY = -2`;

	$user->friend(Voodoo\VoodOrm::REL_HASMANY);

**STRING**: Having a tring as an argument will be used as a foreign key name

	$user->friend("friend_id");
	

**ARRAY** : Array will be used as a WHERE condition. Array must be a key/value matching the fields in the table

	$user->friend(array(
		"friendship_start_time >= " => date("Y-m-d")
	));

**CALLBACK**: Callback is a function to run on the results

	$user->friend(function($data){

		$tableData = array();

		foreach($data as $d) {
			$tableData[] = array_merge(
								$data,
								array("visits_count"=>$d[visits_count] + 1)
							); 
		}
		return $tableData;
	}); 

Now do the Pot Pourri!

	$user->friend(Voodoo\VoodOrm::REL_HASONE, 

					"friend_id", 

					array("friendship_start_time >= " => date("Y-m-d"),

					function($data){
					
							$tableData = array();
					
							foreach($data as $d) {
								$tableData[] = array_merge(
													$data,
													array("visits_count"=>$d[visits_count] + 1)
												); 
							}
							return $tableData;
						}			
	));



	$user->friend(NUMBER, STRING, ARRAY, CALLBACK);

---

## Table Structure

Table structure allows to define the table structure in the database. It is set on the constructor

	new VoodOrm($PDO, $primaryKey = 'id', $foreignKey = '%s_id')

PRIMARYKEY : by default is set to `id` but can be anything

FOREIGNKEY : Is the foreign key. By default it is set %s_id where %s is the table name. So a table user, in a friend table the foreign key will be `user_id`

You can also use `setStructure($primaryKey, $foreignKey)` to set the structure.

	$DB = new VoodOrm($PDO);
	$DB->setStructure("id", "%s_id");

**It is important that a table structure is set, so VoodOrm can identify the primary and foreign keys for relationship.**

---

## Other Methods

With the code below:

$users = $DB->table('users');

#### *string* ***VoodOrm::getPrimaryKeyName()***
	
	$users->getPrimaryKeyName();

Return the primary key name. Most of the time it will be `id`


#### *string* ***VoodOrm::getForeignKeyName()***

	$users->getForeignKeyName();

Return the foreign key name. Based on the table stucture above, it will be `user_id`


### *string* ***VoodOrm::getTableName()*** 

Returns the table name

	$tableName = $users->getTableName();


### *VoodOrm* ***VoodOrm::tableAlias(*** *string $alias* ***)*** 
To set a name as alias of the table

	$users->tableAlias("user");


### *string* ***VoodOrm::getTableAlias()*** 

Returns the table alias name

	$alias = $users->getTableAlias();


### *Array* ***VoodOrm::getStructure()*** 

Return the structure that was set

	$structure = $users->getStructure();

### *Bool* ***VoodOrm::isSingleRow()*** 

Return true or false if an entry is a single row

	$user = $users->findOne(123);

	if ($user->isSingleRow()) {
		// do something here
	}

### *String* ***VoodOrm::NOW()*** 

It returns the current DateTime: Y-M-D H:i:s

---

## Extends VoodOrm to your Model

This is an example below on how you can setup your models with VoodOrm in real world applications. We'll setup the models, and have a controller retrieve them based on an MVC application. The application is base on [Voodoo](http://github.com/mardix/Voodoo), a slim Modular MVC framework in PHP.


**Lib/Model/BaseModel.php:** 

*The base model contains the connection to the DB, and extra methods can be added. BaseModel will be extended in the model classes*

	<?php

	namespace Model;

	use Voodoo;

	abstract class BaseModel extends Voodoo\VoodOrm
	{
		private static $pdo = null;

		// setup the DB connection
		public function __construct()
		{
			if (! self::$pdo) {
				self::$pdo = new PDO("mysql:host=localhost;dbname=$dbname", $username, $password);
			    self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);				
			}

			parent::__construct(self::$pdo);
	        $instance = parent::table($this->tableName);
	        $this->table_name = $instance->getTablename();			
		}
	}


**Lib/Model/Diskoteka/Artist.php**

	<?php

	namespace Model\Diskoteka;

	use Lib\Model;

	class Artist extends Model\BaseModel
	{
		protected $tableName = "artist";

		// We concat the first and last name
		public function getName()
		{
			return $this->first_name." ".$this->last_name; 
		}
	}

**Lib/Model/Diskoteka/Album.php**

	<?php

	namespace Model\Diskoteka;

	use Lib\Model;

	class Album extends Model\BaseModel
	{
		protected $tableName = "album";

		// Returns the VoodOrm object to do more in the query
		public function getSongs()
		{
			return (new Song)->where("album_id", $this->getPK());
		}
	}


**Lib/Model/Diskoteka/Song.php**

	<?php

	namespace Model\Diskoteka;

	use Lib\Model,
		Closure;

	class Song extends Model\BaseModel
	{
		protected $tableName = "song";

		public function getArtistName()
		{
			return $this->artist_first_name;
		}

		public function getAlbumTitle()
		{
			return $this->album_title;
		}

		// We modify the find() to join this table to album and artist tables
		public function find(Closure $callback = null) 
		{
			$this->tableAlias("song")
				->select("song.*")
				->select("album.title As album_title")
				->select("artist.first_name AS artist_first_name")
				->leftJoin(new Artist, "artist.id = song.artist_id", "artist")
				->leftJoin(new Album, "album.id = song.album_id", "album");

			retun parent::find($callback);
		}
	}



**App/Www/Main/Controller/Index.php**

*Using [Voodoo](http://github.com/mardix/Voodoo), we'll create a controller to use the models*

	<?php

	namespace App\Www\Main\Controller;
	
	use Voodoo,
		Lib\Model\Diskoteka;

	class Index extends Voodoo\Core\Controller
	{
		/**
		 * List all songs, which will include the the artist name and album name
		 * http://the-url/
		 */
		public function actionIndex()
		{
			$allSongs = (new Diskoteka\Song);
			$allSongs->orderBy("title", "ASC");

			$songs = [];
			foreach ($allSongs as $song) {
				$songs[] = [
					"id" => $song->getPK()
					"title" => $song->title,
					"albumTitle" => $song->getAlbumTitle(),
					"artistName" => $song->getArtistName()
				];				
			}
			$this->view()->assign("songs", $songs);
		}

		/**
		 * Simply get the Artist info
		 * http://the-url/artist/59
		 */
		public function actionArtist()
		{
			$id = $this->getSegment(1); // -> 59
			$artist = (new Diskoteka\Artist)->findOne($id);	
			$countAlbums = (new Diskoteka\Album)
										->where("artist_id", $id)
										->count();
	
			$this->view()->assign([
				"name" => $artist->getName(),
				"countAlbums" => $countAlbums
			]);	
		}


		/**
		 * Get the song info, with album basic info
		 * http://the-url/song/1637
		 */
		public function actionSong()
		{
			$id = $this->getSegment(1); // -> 1637
			$song = (new Diskoteka\Song)->findOne($id);

			if ($song) {
				$this->view()->assign([
					"id" => $song->getPK()
					"title" => $song->title,
					"albumTitle" => $song->getAlbumTitle(),
					"artistName" => $song->getArtistName()
				]);
			}
		}

		/**
		 * Get the album info including all songs
		 * http://your-url.com/album/437
		 */
		public function actionAlbum()
		{
			$id = $this->getSegment(1);
			$album = (new Diskoteka\Album)->findOne($id);
			
			$allSongs = $album->getSongs();
			$albumSongs = [];
			foreach ($allSongs as $song) {
				$albumSongs[] = [
					"id" => $song->getPK(),
					"title" => $song->title
				];
			}
			$this->view()->assign([
				"album" => [
					"id" => $album->getPK(),
					"title" => $album->title
				],
				"songs" => $albumSongs
			]);
		}

	}

---

Contributers 

Thank you for your interest in VoodOrm. 

If you would like to contribute, please do a pull request.

VoodOrm follows closely PSR-2

---
(c) This Year Mardix :) 


