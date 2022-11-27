**Русскоязычная документация находится [тут](README_rus.md).**

---

Getting the Library
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
      native libraries [NOT PROVIDED] (https://qna.habr.com/q/22669).
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


Типы заполнителей и типы параметров SQL-запроса
---

Типы заполнителей и их предназначение описываются ниже. Прежде чем знакомиться с типами заполнителей, необходимо понять
как работает механизм библиотеки Database.

```php
 $db->query("SELECT ?i", 123); 
```

SQL-запрос после преобразования шаблона:

```sql
SELECT 123
```

В процессе исполнения этой команды *библиотека проверяет, является ли аргумент `123` целочисленным значением*.
Заполнитель `?i` представляет собой символ `?` (знак вопроса) и первую букву слова `integer`. Если аргумент
действительно представляет собой целочисленный тип данных, то в шаблоне SQL-запроса заполнитель `?i` заменяется на
значение `123` и SQL передается на исполнение.

Поскольку PHP слаботипизированный язык, то вышеописанное выражение эквивалентно нижеописанному:

```php
 $db->query("SELECT ?i", '123'); 
 ```

SQL-запрос после преобразования шаблона:

 ```sql
 SELECT 123
 ```

т.е. числа (целые и с плавающей точкой) представленные как в своем типе, так и в виде `string` — равнозначны с точки
зрения библиотеки.

### Приведение к типу заполнителя

 ```php
  $db->query("SELECT ?i", '123.7'); 
  ```

SQL-запрос после преобразования шаблона:

  ```sql
  SELECT 123
  ```

В данном примере заполнитель целочисленного типа данных ожидает значение типа `integer`, а передается `double`. **
По-умолчанию библиотека работает в режиме приведения типов, что дало в итоге приведение типа `double` к `int`**.

Режимы работы библиотеки и принудительное приведение типов
----
Существует два режима работы библиотеки:

* **Mysql::MODE_STRICT** — строгий режим соответствия типа заполнителя и типа аргумента. В режиме MODE_STRICT аргументы
  должны соответствовать типу заполнителя. Например, попытка передать в качестве аргумента значение `55.5` или `'55.5'`
  для заполнителя целочисленного типа `?i` приведет к выбросу исключения:

```php
// устанавливаем строгий режим работы
$db->setTypeMode(Mysql::MODE_STRICT);
// это выражение не будет исполнено, будет выброшено исключение:
// Попытка указать для заполнителя типа int значение типа double в шаблоне запроса SELECT ?i
$db->query('SELECT ?i', 55.5);
```

* **Mysql::MODE_TRANSFORM** — режим преобразования аргумента к типу заполнителя при несовпадении типа заполнителя и типа
  аргумента. Режим MODE_TRANSFORM установлен по-умолчанию и является "толерантным" режимом — при несоответствии типа
  заполнителя и типа аргумента не генерирует исключение, а **пытается преобразовать аргумент к нужному типу заполнителя
  посредством самого языка PHP**. К слову сказать, я, как автор библиотеки, всегда использую именно этот режим, строгий
  режим (Mysql::MODE_STRICT) я сделал чисто "по фану" и в реальной работе никогда не использовал.

**Допускаются следующие преобразования в режиме Mysql::MODE_TRANSFORM:**

* **К типу `int` (заполнитель `?i`) приводятся**
    * числа с плавающей точкой, представленные как `string` или тип `double`
    * `bool` TRUE преобразуется в `int(1)`, FALSE преобразуется в `int(0)`
    * null преобразуется в `int(0)`
* **К типу `double` (заполнитель `?d`) приводятся**
    * целые числа, представленные как `string` или тип `int`
    * `bool` TRUE преобразуется в `float(1)`, FALSE преобразуется в `float(0)`
    * `null` преобразуется в `float(0)`
* **К типу `string` (заполнитель `?s`) приводятся**
    * `bool` TRUE преобразуется в `string(1) "1"`, FALSE преобразуется в `string(1) "0"`. Это поведение отличается от
      приведения типа `bool` к `int` в PHP, т.к. зачастую, на практике, булев тип записывается в MySql именно как число.
    * значение типа `numeric` преобразуется в строку согласно правилам преобразования PHP
    * `NULL` преобразуется в `string(0) ""`
* **К типу `null` (заполнитель `?n`) приводятся**
    * любые аргументы.
* Для массивов, объектов и ресурсов преобразования не допускаются.

Какие типы заполнителей представлены в библиотеке Database?
---

### `?i` — заполнитель целого числа

```php
$db->query('SELECT * FROM `users` WHERE `id` = ?i', $value); 
```

**ВНИМАНИЕ!** Если вы оперируете числами, выходящими за пределы PHP_INT_MAX, то:

* Оперируйте ими исключительно как строками в своих программах.
* Не используйте данный заполнитель, используйте заполнитель строки `?s` (см. ниже). Дело в том, что числа, выходящие за
  пределы PHP_INT_MAX, PHP интерпретирует как числа с плавающей точкой. Парсер библиотеки постарается преобразовать
  параметр к типу int, в итоге «*результат будет неопределенным, так как float не имеет достаточной точности, чтобы
  вернуть верный результат. В этом случае не будет выведено ни предупреждения, ни даже замечания!*»
  — <a href="http://php.net/manual/ru/language.types.integer.php#language.types.integer.casting.from-float">php.net</a>.

### `?d` — заполнитель числа с плавающей точкой

```php
$db->query('SELECT * FROM `prices` WHERE `cost` = ?d', $value); 
```

**ВНИМАНИЕ!** Если вы используете библиотеку для работы с типом данных `double`, установите соответствующую локаль, что
бы разделитель целой и дробной части был одинаков как на уровне PHP, так и на уровне СУБД.

### `?s` — заполнитель строкового типа

Значение аргументов экранируются с помощью функции PHP `mysqli_real_escape_string()`:

```php
 $db->query('SELECT "?s"', "Вы все пидарасы, а я - Д'Артаньян!");
 ```

SQL-запрос после преобразования шаблона:

```sql
SELECT "Вы все пидарасы, а я - Д\'Артаньян!"
```

### `?S` — заполнитель строкового типа для подстановки в SQL-оператор LIKE

Значение аргументов экранируются с помощью функции PHP `mysqli_real_escape_string()` + экранирование спецсимволов,
используемых в операторе LIKE (`%` и `_`):

```php
 $db->query('SELECT "?S"', '% _'); 
 ```

SQL-запрос после преобразования шаблона:

 ```sql
 SELECT "\% \_"
 ```

### `?n` — заполнитель `NULL` типа

Значение любых аргументов игнорируются, заполнители заменяются на строку `NULL` в SQL запросе:

```php
 $db->query('SELECT ?n', 123); 
 ```

SQL-запрос после преобразования шаблона:

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
