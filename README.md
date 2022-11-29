**![](https://upload.wikimedia.org/wikipedia/en/thumb/f/f3/Flag_of_Russia.svg/23px-Flag_of_Russia.svg.png) [Русскоязычная документация находится тут](README_rus.md) ![](https://upload.wikimedia.org/wikipedia/en/thumb/f/f3/Flag_of_Russia.svg/23px-Flag_of_Russia.svg.png)**

---

## ![](https://upload.wikimedia.org/wikipedia/en/thumb/a/ae/Flag_of_the_United_Kingdom.svg/23px-Flag_of_the_United_Kingdom.svg.png) Getting the Library

You can [download it as an archive](https://github.com/Vasiliy-Makogon/Database/archive/master.zip), clone from this
site, or download via composer ([link to packagist.org](https://packagist.org/packages/krugozor/database)):
```
composer require krugozor/database
```


## What is `krugozor/database`?

`krugozor/database` is a PHP 8.0 class library for simple, convenient, fast and secure work with the MySql database, using
the PHP extension [mysqli](https://www.php.net/en/mysqli).


### Why do we need a self-written class for MySql if PHP has a PDO abstraction and a mysqli extension?

The main disadvantages of all libraries for working with the mysql database in PHP are::

* **Verbosity**
    * Developers have two options to prevent SQL injections:
        * Use [prepared queries](https://www.php.net/manual/en/mysqli.quickstart.prepared-statements.php).
        * Manually escape the parameters going into the body of the SQL query. String parameters run
          via [mysqli_real_escape_string](https://www.php.net/manual/en/mysqli.real-escape-string.php) and the expected
          convert numeric parameters to the appropriate types - `int` and `float`.
    * Both approaches have huge drawbacks:
        * Prepared
  queries are [terribly verbose](https://www.php.net/manual/en/mysqli.prepare.php#refsect1-mysqli.prepare-examples). Use "out of the box" PDO abstraction or mysqli extension, no aggregation
  all methods for obtaining data from the DBMS is simply impossible - in order to get the value from the table, you need
  write at least 5 lines of code! And so on for every request!
        * Manual escaping of parameters going into the body of an SQL query is not even discussed. A good programmer lazy programmer. Everything should be as automated as possible..
* **Unable to get SQL query for debugging**
    * To understand why the SQL query does not work in the program, you need to debug it - find either a logical or
      syntax error. To find an error, you need to "see" the SQL query itself, which the database "swears" at, with
      parameters substituted into its body. Those. to have formed high-grade SQL. If the developer is using PDO,
      with prepared queries, then it's... IMPOSSIBLE! There are no most convenient mechanisms for this in
      native libraries [NOT PROVIDED](https://qna.habr.com/q/22669).
      It remains either to pervert, or to climb into the database log.


### Solution: `krugozor/database` is a class for working with MySql

1. Eliminates verbosity - instead of 3 or more lines of code to execute one request when using the "native" library, you write only one.
2. Screens all parameters that go to the request body, according to the specified type of placeholders - reliable protection against SQL injections.
3. Does not replace the functionality of the "native" mysqli adapter, but simply complements it.
4. Expandable. In fact, the library provides only a parser and the execution of a SQL query with guaranteed protection against SQL injections. You can inherit from any library class and use both the library mechanisms and the `mysqli` and `mysqli_result` mechanisms to create the methods you need to work with.


### What is NOT the `krugozor/database` library?

Most wrappers for various database drivers are a bunch of useless code with a disgusting
architecture. Their authors, not understanding the practical purpose of their wrappers themselves, turn them into a kind of builders
queries (sql builder), ActiveRecord libraries and other ORM solutions.

The `krugozor/database` library is none of the above. This is just a convenient tool for working with regular SQL within the framework
MySQL DBMS - and no more!


## What are placeholders?

**Placeholders** — special *typed markers* that are written in the SQL query string *instead of
explicit values (query parameters)*. And the values themselves are passed "later", as subsequent arguments to the main
a method that executes a SQL query:

```php
<?php
// Let's assume you installed the library via composer
require  './vendor/autoload.php';

use Krugozor\Database\Mysql;

// Connecting to a DBMS and getting a "wrapper" object over mysqli - \Krugozor\Database\Mysql
$db = Mysql::create("localhost", "root", "password")
      // Error output language - English
      ->setErrorMessagesLang('en')
      // Database selection
      ->setDatabaseName("test")
      // Encoding selection
      ->setCharset("utf8")
      // Enable storage of all SQL queries for reporting/debugging/statistics
      ->setStoreQueries(true);

// Getting a result object \Krugozor\Database\Statement
// \Krugozor\Database\Statement - "wrapper" over an object mysqli_result
$result = $db->query("SELECT * FROM `users` WHERE `name` = '?s' AND `age` = ?i", "d'Artagnan", 41);

// We receive data (in the form of an associative array, for example)
$data = $result->fetchAssoc();

// SQL query not working as expected?
// Not a problem - print it and see the generated SQL query,
// which will already be with the parameters substituted into its body:
echo $db->getQueryString(); // SELECT * FROM `users` WHERE `name` = 'd\'Artagnan' AND `age` = 41
```

SQL query parameters passed through the *placeholders* system are processed by special escaping mechanisms, in
depending on the type of placeholders. Those. you no longer need to wrap variables in escaping functions
type `mysqli_real_escape_string()` or cast them to a numeric type as before:

```php
<?php
// Previously, before each request to the DBMS, we did
// something like this (and many people still don't do it):
$id = (int) $_POST['id'];
$value = mysqli_real_escape_string($mysql, $_POST['value']);
$result = mysqli_query($mysql, "SELECT * FROM `t` WHERE `f1` = '$value' AND `f2` = $id");
```

Now it has become easy to write queries, quickly, and most importantly, the `krugozor/database` library completely prevents any possible
SQL injections.

### Introduction to placeholder system

The types of placeholders and their purpose are described below. Before getting acquainted with placeholder types, it is necessary to understand how the mechanism of the `krugozor/database` library works. Example:

```php
 $db->query("SELECT ?i", 123); 
```
SQL query after template conversion:
```sql
SELECT 123
```
During the execution of this command *the library checks if the argument `123` is an integer value*. The placeholder `?i` is the character `?` (question mark) and the first letter of the word `integer`. If the argument is indeed an integer data type, then the placeholder `?i` in the SQL query template is replaced with the value `123` and the SQL is passed for execution.

Since PHP is a weakly typed language, the above expression is equivalent to the following:

```php
 $db->query("SELECT ?i", '123'); 
 ```
SQL query after template conversion:
 ```sql
 SELECT 123
 ```

that is, numbers (integer and floating point) represented both in their type and in the form of `string` are equivalent from the point of view of the library.


### Library Modes and Forced Type Casting

There are two modes of library operation:

* **Mysql::MODE_STRICT - strict match mode for placeholder type and argument type**.
  In `Mysql::MODE_STRICT` mode, *arguments must match the placeholder type*. For example, an attempt to pass the value `55.5` or `'55.5'` as an argument for an integer placeholder `?i` will result in an exception being thrown:

```php
// set strict mode
$db->setTypeMode(Mysql::MODE_STRICT);
// this expression will not be executed, an exception will be thrown:
// Trying to set placeholder type "int" to value type "double" in query template "SELECT ?i"
$db->query('SELECT ?i', 55.5);
```

* **Mysql::MODE_TRANSFORM — argument conversion mode to placeholder type when placeholder type and argument type do not match.** The `Mysql::MODE_TRANSFORM` mode is set by default and is a "tolerant" mode - if the placeholder type and the argument type do not match, it does not throw an exception, but *tryes to convert the argument to the desired placeholder type using the PHP language itself*. By the way, I, as the author of the library, always use this particular mode, I have never used strict mode (`Mysql::MODE_STRICT`) in real work, but perhaps you will need it specifically.

**The following transformations are allowed in `Mysql::MODE_TRANSFORM`:**

* **Cast to type `int` (placeholder `?i`)**
  * floating point numbers represented both in `string` and `double` types
  * `bool` TRUE is converted to `int(1)`, FALSE is converted to `int(0)`
  * `null` is converted to `int(0)`
* **Cast to type `double` (placeholder `?d`)**
  * integers represented in both `string` and `int` types
  * `bool` TRUE becomes `float(1)`, FALSE becomes `float(0)`
  * `null` is converted to `float(0)`
* **Cast to type `string` (placeholder `?s`)**
  * `bool` TRUE is converted to `string(1) "1"`, FALSE is converted to `string(1) "0"`. This behavior is different from casting `bool` to `int` in PHP, as often, in practice, the boolean type is written in MySql as a number.
  * a `numeric` value is converted to a string according to PHP's conversion rules
  * `null` is converted to `string(0) ""`
* **Cast to type `null` (placeholder `?n`)**
  * any arguments.
* For arrays, objects and resources, conversions are not allowed.

**ATTENTION!** The following explanation of the library will go on assuming that the `Mysql::MODE_TRANSFORM` mode is activated.

### What types of placeholders are provided in the `krugozor/database` library?


#### `?i` — integer placeholder

```php
$db->query('SELECT * FROM `users` WHERE `id` = ?i', $_POST['user_id']); 
```

**ATTENTION!** If you operate on numbers that are outside the limits of `PHP_INT_MAX`, then:

* Operate them exclusively as strings in your programs.
* Don't use this placeholder, use the string placeholder `?s` (see below). The point is that numbers beyond
  limits `PHP_INT_MAX`, PHP interprets as floating point numbers. The library parser will try to convert
  parameter to type `int`, as a result "*the result will be undefined, since the float does not have sufficient precision to
  return the correct result. In this case, neither a warning nor even a remark will be displayed!*” — [php.net](https://www.php.net/manual/en/language.types.integer.php#language.types.integer.casting.from-float).

#### `?d` — floating point placeholder

```php
$db->query('SELECT * FROM `prices` WHERE `cost` = ?d', 12.56); 
```

**ATTENTION!** If you are using a library to work with the `double` data type, set the appropriate locale so that
If the separator of the integer and fractional parts were the same both at the PHP level and at the DBMS level.

#### `?s` — string type placeholder

The argument values are escaped using the `mysqli::real_escape_string()` method:

```php
 $db->query('SELECT "?s"', "You are all fools, and I am d'Artagnan!");
 ```

SQL query after template conversion:

```sql
SELECT "You are all fools, and I am d\'Artagnan!"
```

#### `?S` — string type placeholder for substitution in the SQL LIKE operator

Argument values are escaped using the `mysqli::real_escape_string()` method + escaping special characters used in the LIKE operator (`%` and `_`):

```php
 $db->query('SELECT "?S"', '% _'); 
 ```

SQL query after template conversion:

 ```sql
 SELECT "\% \_"
 ```

#### `?n` — placeholder `NULL` type

The value of any arguments is ignored, placeholders are replaced with the string `NULL` in the SQL query:

```php
 $db->query('SELECT ?n', 123); 
 ```

SQL query after template conversion:

 ```sql
 SELECT NULL
 ```

#### `?A*` — associative set placeholder from an associative array, generating a sequence of pairs of the form `key = value`

where the character `*` is one of the placeholders:

* `i` (integer placeholder)
* `d` (float placeholder)
* `s` (string type placeholder)

the rules for conversion and escaping are the same as for the single scalar types described above. Example:

```php
$db->query('INSERT INTO `test` SET ?Ai', ['first' => '123', 'second' => 1.99]);
```
SQL query after template conversion:
```sql
INSERT INTO `test` SET `first` = "123", `second` = "1"
```

#### `?a*` - set placeholder from a simple (or also associative) array, generating a sequence of values

where `*` is one of the types:
* `i` (integer placeholder)
* `d` (float placeholder)
* `s` (string type placeholder)

the rules for conversion and escaping are the same as for the single scalar types described above. Example:

```php
 $db->query('SELECT * FROM `test` WHERE `id` IN (?ai)', [123, 1.99]);
```
SQL query after template conversion:
```sql
 SELECT * FROM `test` WHERE `id` IN ("123", "1")
```


#### `?A[?n, ?s, ?i, ...]` — associative set placeholder with an explicit indication of the type and number of arguments, generating a sequence of `key = value` pairs

Example:
```php
$db->query('INSERT INTO `users` SET ?A[?i, "?s"]', ['age' => 41, 'name' => "d'Artagnan"]);
```
SQL query after template conversion:
```sql
 INSERT INTO `users` SET `age` = 41,`name` = "d\'Artagnan"
```

#### `?a[?n, ?s, ?i, ...]` — set placeholder with an explicit indication of the type and number of arguments, generating a sequence of values

Example:

```php
$db->query('SELECT * FROM `users` WHERE `name` IN (?a["?s", "?s"])', ['Daniel O"Neill', "d'Artagnan"]);
```
SQL query after template conversion:
```sql
 SELECT * FROM `users` WHERE `name` IN ("Daniel O\"Neill", "d\'Artagnan")
```


#### `?f` — table or field name placeholder

This placeholder is intended for cases where the name of a table or field is passed in the query as a parameter. Field and table names are framed with an apostrophe:

```php
 $db->query('SELECT ?f FROM ?f', 'name', 'database.table_name');
 ```
SQL query after template conversion:
 ```sql
  SELECT `name` FROM `database`.`table_name`
 ```


### Delimiting quotes

**The library requires the programmer to follow the SQL syntax.** This means that the following query will not work:

```php
$db->query('SELECT CONCAT("Hello, ", ?s, "!")', 'world');
```

— placeholder `?s` must be enclosed in single or double quotes:

```php
$db->query('SELECT concat("Hello, ", "?s", "!")', 'world');
```

SQL query after template conversion:

```sql
SELECT concat("Hello, ", "world", "!")
```

For those who are used to working with PDO, this will seem strange, but implementing a mechanism that determines whether it is necessary to enclose the placeholder value in quotes in one case or not is a very non-trivial task that requires writing a whole parser.


## Examples of working with the library

in the process....
