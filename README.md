**![](https://upload.wikimedia.org/wikipedia/en/thumb/f/f3/Flag_of_Russia.svg/23px-Flag_of_Russia.svg.png) Русскоязычная документация находится [тут](README_rus.md).**

---

![](https://upload.wikimedia.org/wikipedia/en/thumb/a/ae/Flag_of_the_United_Kingdom.svg/23px-Flag_of_the_United_Kingdom.svg.png) Getting the Library
---
You can [download it as an archive](https://github.com/Vasiliy-Makogon/Database/archive/master.zip), clone from this
site, or download via composer ([link to packagist.org](https://packagist.org/packages/krugozor/database)):
```
composer require krugozor/database
```


What is `krugozor/database`?
---

`krugozor/database` is a PHP 8.0 class library for simple, convenient, fast and secure work with the MySql database, using
the PHP extension [mysqli](https://www.php.net/en/mysqli).

Why do we need a self-written class for MySql if PHP has a PDO abstraction and a mysqli extension?
---

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

Solution: `krugozor/database` is a class for working with MySql
---

1. Eliminates verbosity - instead of 3 or more lines of code to execute one request when using the "native" library, you write only one.
2. Screens all parameters that go to the request body, according to the specified type of placeholders - reliable protection against SQL injections.
3. Does not replace the functionality of the "native" mysqli adapter, but simply complements it.

### What is NOT the `krugozor/database` library?

Most wrappers for various database drivers are a bunch of useless code with a disgusting
architecture. Their authors, not understanding the practical purpose of their wrappers themselves, turn them into a kind of builders
queries (sql builder), ActiveRecord libraries and other ORM solutions.

The `krugozor/database` library is none of the above. This is just a convenient tool for working with regular SQL within the framework
MySQL DBMS - and no more!


What are placeholders?
---

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
      // Enable storage of executed queries for reporting/debugging/statistics
      ->setStoreQueries(true);

// Getting a result object \Krugozor\Database\Statement
// \Krugozor\Database\Statement - "wrapper" over an object mysqli_result
$result = $db->query("SELECT * FROM `users` WHERE `name` = '?s' AND `age` = ?i", "John", 30);

// We receive data (in the form of an associative array, for example)
$data = $result->fetchAssoc();

// SQL query not working as expected?
// Not a problem - print it and see the generated SQL query,
// which will already be with the parameters substituted into its body:
echo $db->getQueryString(); // SELECT * FROM `users` WHERE `name` = 'Jon' AND `age` = 30
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

Introduction to placeholder system
---

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


Library Modes and Forced Type Casting
----
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

* **Mysql::MODE_TRANSFORM — argument conversion mode to placeholder type when placeholder type and argument type do not match.** throws an exception, and *attempts to convert the argument to the correct placeholder type using PHP itself*. By the way, I, as the author of the library, always use this particular mode, I have never used strict mode (`Mysql::MODE_STRICT`) in real work, but perhaps you will need it specifically.

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

What types of placeholders are provided in the `krugozor/database` library?
---

### `?i` — integer placeholder

```php
$db->query('SELECT * FROM `users` WHERE `id` = ?i', $_POST['user_id']); 
```

**WARNING!** If you operate on numbers that are outside the limits of `PHP_INT_MAX`, then:

* Operate them exclusively as strings in your programs.
* Don't use this placeholder, use the string placeholder `?s` (see below). The point is that numbers beyond
  limits `PHP_INT_MAX`, PHP interprets as floating point numbers. The library parser will try to convert
  parameter to type `int`, as a result "*the result will be undefined, since the float does not have sufficient precision to
  return the correct result. In this case, neither a warning nor even a remark will be displayed!*” — [php.net](https://www.php.net/manual/en/language.types.integer.php#language.types.integer.casting.from-float).

### `?d` — floating point placeholder

```php
$db->query('SELECT * FROM `prices` WHERE `cost` = ?d', 12.56); 
```

**WARNING!** If you are using a library to work with the `double` data type, set the appropriate locale so that
If the separator of the integer and fractional parts were the same both at the PHP level and at the DBMS level.

### `?s` — string type placeholder

The argument values are escaped using the `mysqli::real_escape_string()` method:

```php
 $db->query('SELECT "?s"', "You are all fools, and I am D'Artagnan!");
 ```

SQL query after template conversion:

```sql
SELECT "You are all fools, and I am D\'Artagnan!"
```

### `?S` — string type placeholder for substitution in the SQL LIKE operator

Argument values are escaped using the `mysqli::real_escape_string()` method + escaping special characters used in the LIKE operator (`%` and `_`):

```php
 $db->query('SELECT "?S"', '% _'); 
 ```

SQL query after template conversion:

 ```sql
 SELECT "\% \_"
 ```

### `?n` — placeholder `NULL` type

The value of any arguments is ignored, placeholders are replaced with the string `NULL` in the SQL query:

```php
 $db->query('SELECT ?n', 123); 
 ```

SQL query after template conversion:

 ```sql
 SELECT NULL
 ```

### `?A*` — заполнитель ассоциативного множества из ассоциативного массива, генерирующий последовательность пар `ключ = значение`

Пример: `"key_1" = "val_1", "key_2" = "val_2", ..., "key_N" = "val_N"`

где **\*** после заполнителя — один из типов:

* `i` (заполнитель целого числа)
* `d` (заполнитель числа с плавающей точкой)
* `s` (заполнитель строкового типа)

правила преобразования и экранирования такие же, как и для одиночных скалярных типов, описанных выше. Пример:

```php
$db->query('INSERT INTO `test` SET ?Ai', ['first' => 123, 'second' => 1.99]);
```

SQL-запрос после преобразования шаблона:

```sql
INSERT INTO `test`
SET `first` = "123", `second` = "1"
```

### `?a*` — заполнитель множества из простого (или также ассоциативного) массива, генерирующий последовательность значений

Пример: `"val_1", "val_2", ..., "val_N"`

где **\*** после заполнителя — один из типов:

* `i` (заполнитель целого числа)
* `d` (заполнитель числа с плавающей точкой)
* `s` (заполнитель строкового типа)

правила преобразования и экранирования такие же, как и для одиночных скалярных типов, описанных выше. Пример:

```php
 $db->query('SELECT * FROM `test` WHERE `id` IN (?ai)', [123, 1.99]);
```

SQL-запрос после преобразования шаблона:

```sql
 SELECT *
 FROM `test`
 WHERE `id` IN ("123", "1")
```

### `?A[?n, ?s, ?i, ...]` — заполнитель ассоциативного множества с явным указанием типа и количества аргументов, генерирующий последовательность пар `ключ = значение`

Пример:

```php
 $db->query('INSERT INTO `test` SET ?A[?i, "?s"]', ['first' => 1.3, 'second' => "Д'Артаньян"]);
```

SQL-запрос после преобразования шаблона:

```sql
 INSERT INTO `test`
 SET `first` = 1,`second` = "Д\'Артаньян"
```

### `?a[?n, ?s, ?i]` — заполнитель множества с явным указанием типа и количества аргументов, генерирующий последовательность значений

```php
 $db->query('SELECT * FROM `test` WHERE `value` IN (?a[?i, "?s"])', [1.3, "Д'Артаньян"]);
```

SQL-запрос после преобразования шаблона:

```sql
 SELECT *
 FROM `test`
 WHERE `value` IN (1, "Д\'Артаньян")
```

### `?f` — заполнитель имени таблицы или поля

Данный заполнитель предназначен для случаев, когда имя таблицы или поля передается в запросе через параметр. Имена полей
и таблиц обрамляется символом апостроф:

```php
 $db->query('SELECT ?f FROM ?f', 'name', 'database.table_name');
 ```

SQL-запрос после преобразования шаблона:

 ```sql
  SELECT `name`
  FROM `database`.`table_name`
 ```

Ограничивающие кавычки
---

Библиотека **требует** от программиста соблюдения синтаксиса SQL. Это значит, что следующий запрос работать не будет:

```php
$db->query('SELECT CONCAT("Hello, ", ?s, "!")', 'world');
```

— заполнитель `?s` необходимо взять в одинарные или двойные кавычки:

```php
$db->query('SELECT concat("Hello, ", "?s", "!")', 'world');
```

SQL-запрос после преобразования шаблона:

```sql
SELECT concat("Hello, ", "world", "!")
```

Для тех, кто привык работать с PDO это покажется странным, но реализовать механизм, определяющий, нужно ли в одном
случае заключать значение заполнителя в кавычки или нет — очень нетривиальная задача, трубующая написания целого
парсера.


Примеры работы с библиотекой
---

```php
// Предположим, что установили библиотеку через composer 
require  './vendor/autoload.php';

use Krugozor\Database\Mysql;

// Подключение к СУБД, выбор кодировки и базы данных.
$db = Mysql::create('localhost', 'root', '')
           ->setCharset('utf8')
           ->setDatabaseName('test');
```

```php
// Создаем таблицу пользователей с полями:
// Первичный ключ, имя пользователя, возраст, адрес
$db->query('
    CREATE TABLE IF NOT EXISTS users(
        id int unsigned not null primary key auto_increment,
        name varchar(255),
        age tinyint,
        adress varchar(255)
    )
');
```

### Примеры для понимания сути заполнителей

#### Различные варианты INSERT:

##### Простая вставка данных через заполнители разных типов:

```php
$db->query("INSERT INTO `users` VALUES (?n, '?s', ?i, '?s')", null, 'Иоанн Грозный', '54', 'в палатах');
```

SQL-запрос после преобразования шаблона:

```sql
INSERT INTO `users`
VALUES (NULL, 'Иоанн Грозный', 54, 'в палатах')
```

##### Вставка значений через заполнитель ассоциативного множества типа string:

```php
$user = array('name' => 'Пётр', 'age' => '30', 'adress' => "ООО 'Рога и Копыта'");
$db->query('INSERT INTO `users` SET ?As', $user);
```

SQL-запрос после преобразования шаблона:

```sql
INSERT INTO `users`
SET `name` = "Пётр", `age` = "30", `adress` = "ООО \'Рога и Копыта\'"
```

##### Вставка значений через заполнитель ассоциативного множества с явным указанием типа и количества аргументов:

```php
$user = array('name' => "Д'Артаньян", 'age' => '19', 'adress' => 'замок Кастельмор');
$db->query('INSERT INTO `users` SET ?A["?s", ?i, "?s"]', $user);
```

SQL-запрос после преобразования шаблона:

```sql
INSERT INTO `users`
SET `name` = "Д\'Артаньян",`age` = 19,`adress` = "замок Кастельмор"
```

#### Различные варианты SELECT

##### Укажем некорректный числовой параметр - значение типа double:

```php
$db->query('SELECT * FROM `users` WHERE `id` = ?i', '1.00');
```

SQL-запрос после преобразования шаблона:

```sql
SELECT *
FROM `users`
WHERE `id` = 1
```

##### ---

```php
 $db->query(
    'SELECT id, adress FROM `users` WHERE `name` IN (?a["?s", "?s", "?s"])',
    array('Василий', 'Иван', "Д'Артаньян")
); 
```

SQL-запрос после преобразования шаблона:

```sql
SELECT id, adress
FROM `users`
WHERE `name` IN ("Василий", "Иван", "Д\'Артаньян")
```

##### Имя базы данных, таблицы и поля передаются также, как и аргументы запроса. Не удивляйтесь имени поля '.users.name' - это допустимый для MySql синтаксис:

```php
$db->query(
    'SELECT * FROM ?f WHERE ?f IN (?as) OR `id` IN (?ai)',
    '.users', '.users.name', array('Василий'), array('2', 3.000)
);
```

SQL-запрос после преобразования шаблона:

```sql
SELECT *
FROM.`users`
WHERE.`users`.`name` IN ("Василий") OR `id` IN ("2", "3")
```

### Некоторые возможности API

##### Применение метода queryArguments() - аргументы передаются в виде массива. Это второй, после метода query(), метод запросов в базу:

```php
$sql = 'SELECT * FROM `users` WHERE `name` = "?s" OR `name` = "?s"';
$arguments[] = "Василий";
$arguments[] = "Д'Артаньян";
$result = $db->queryArguments($sql, $arguments);
// Получим количество рядов в результате
$result->getNumRows(); // 2
```

##### Вставить запись, получить последнее значение автоинкрементного поля и количество задействованных рядов:

```php
if ($db->query("INSERT INTO `users` VALUES (?n, '?s', '?i', '?s')", null, 'тест', '10', 'тест')) {
    echo $db->getLastInsertId(); // последнее значение автоинкрементного поля
    echo $db->getAffectedRows(); // количество задействованных рядов
}
```

##### Получить все в виде ассоциативных массивов:

```php
// Получить все...
$res = $db->query('SELECT * FROM users');
// Последовательно получать в виде ассоциативных массивов
while (($data = $res->fetchAssoc()) !== null) {
    print_r($data);
}
```

##### Получить одно значение из выборки:

```php
echo $db->query('SELECT 5 + ?d', '5.5')->getOne(); // 10.5
```

##### Получить все SQL-запросы текущего соединения:

```php
print_r($db->getQueries());
```
