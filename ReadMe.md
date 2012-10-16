
# VoodOrm

---

Name: VoodOrm

version: 0.2.x

License: MIT

Author: [Mardix](http://github.com/mardix)

---
*Documentation is still in construction, please bare with me*

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
- Active Record pattern

## Requirements

- PHP >= 5.3
- PDO

## Error Reporting
VoodOrm does not escalate errors. Non-existing table produces SQL error that is reported by PDO conforming to PDO::ATTR\_ERRMODE. Non-existing columns produces the same E_NOTICE as an attempt to access non-existing key in array.

## What it doesn't do. 

We believe it's best if certain stuff is kept to the developer to do, like caching or data validation. Also data validation can be done at the database level, like 

- No models or entities generation
- No data validation
- No caching
- No database migration


---
## Working with VoodOrm

---
### ***new VoodOrm(*** *PDO $pdo* ***)***

To get started with VoodOrm, you have to setup the PDO connection. We'll be using the variable $DB as the database connection, `$users` as the table, `$friends` as another table throughout this whole tutorial

	$pdo = new PDO("mysql:host=localhost;dbname=$dbname", $username, $password);
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
		 			"timestamp" => $voodorm->Datetime()
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
							 "timestamp" => $voodorm->Datetime()
						),
						array(
							 "name" => "Cesar",
							 "city" => "Atlanta",
						     "state" => "GA",
							 "device" => "mobile",
							 "timestamp" => $voodorm->Datetime()
						),
						array(
							 "name" => "Gaga",
							 "city" => "Port-au-Prince",
						     "state" => "HT",
							 "device" => "computer",
							 "timestamp" => $voodorm->Datetime()
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

You can use `save()` instead of `update()`

	$user->save();


or with *Voodoo::set(Array $data) or Voodoo::set($key, $value)*

	$singleEntry->set('city','Raleigh')->update();

***For multiple entries:***

For multiple entries we'll use `VoodOrm::set()` and `VoodOrm::where()` to specify where to update.

*Voodoo::set(Array $data) or Voodoo::set($key, $value)*

For mass update, we'll set the data to update using `set(Array $data)` and `where($k, $v)`

	$voodorm->set(array(
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

	$singleEntry->delete();

For multiple entries, we will use the `VoodOrm::where()` method to specify where to delete

	$voodorm->where($x, $y)->delete();

---

## Aggregation

VoodOrm gives you access to aggregation methods on your table

---

#### *int* ***VoodOrm::count()***
To count all the entries based on where clause

	$count = $voodorm->where($x, $y)->count();

or for a specific column name

	$count = $voodorm->where($x, $y)->count($columnName);


#### *float* ***VoodOrm::max(*** *string $columnName* ***)***
To get the max of a $columnName based on where() clause

	$max = $voodorm->where($x, $y)->max($columnName);


#### *float* ***VoodOrm::min(*** *string $columnName* ***)***
To get the min of a $columnName based on where() clause

	$min = $voodorm->where($x, $y)->min($columnName);


#### *float* ***VoodOrm::sum(*** *string $columnName* ***)***
To get the sum of a $columnName based on where() clause

	$sum = $voodorm->where($x, $y)->sum($columnName);


#### *float* ***VoodOrm::avg(*** *string $columnName* ***)***
To get the average of a $columnName based on where() clause

	$avg = $voodorm->where($x, $y)->avg($columnName);

#### *mixed* ***VoodOrm::aggregate(*** *string $function* ***)***
To run any aggregation function

	$agg = $voodorm->where($x, $y)->aggregate('GROUP_CONCAT $columnName');

---

## Querying
VoodOrm provides a fluent interface to enable simple queries to be built without writing a single character of SQL. 

Two main methods allow you to get a single entry or multiple entries. 

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
		$user->last_viewed = $users->DateTime();
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
		$user->last_viewed = $users->DateTime();
		$user->save();
	}

`find()` also contains a shortcut when it's called in an iteration such as foreach:

	$allUsers = $users->where('gender', 'male');

	foreach ($allUsers as $user) {
		echo "{$user->name}";

	// On a retrieved entry you can perform update and delete
		$user->last_viewed = $users->DateTime();
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

## Fluent Query Builder

---



## Select

### *VoodOrm* ***VoodOrm::select(*** *$columns = '\*'*  ***)***
To select the fields in the table. If ommitted, VooOrm will fetch all the columns.

	$users->select()

or with selected columns

	$users->select("name, age, last_viewed");

	SELECT name, age, last_viewed

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


## Advanced
to be added



