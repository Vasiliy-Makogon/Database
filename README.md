Rus
---
Класс для простой работы с СУБД MySql с использованием расширения PHP mysqli.

Данный класс использует технологию placeholders - для формирования корректных SQL-запросов, в строке запроса вместо значений пишутся специальные типизированные маркеры - заполнители, а сами данные передаются "позже", в качестве последующих аргументов основного метода, выполняющего SQL-запрос:


```php
$db->query('SELECT * FROM `t` WHERE `name` = "?s" AND `age` = ?i', $_POST['name'], $_POST['age']);
```


Данные, прошедшие через систему placeholders, экранируются специальными функциями экранирования, в зависимости от типа заполнителей. Т.е. вам нет необходимости заключать переменные в функции экранирования типа _mysqli_real_escape_string($value)_ или приводить их к числовому типу через _(int)$value_.

Подробное описание см. в файле <a href="https://github.com/Vasiliy-Makogon/Database/blob/master/Mysql.php">/Mysql.php</a> или на сайте <a href="http://www.database.phpinfo.su/">database.phpinfo.su</a>

Eng
---

Database - class for develop with database mysql, use adapter PHP mysqli and use placeholder, when each literal (argument) in string SQL query escaping without specific PHP functions like a mysqli_real_escape_string(). 

```php
$db->query('SELECT * FROM `t` WHERE `name` = "?s" AND `age` = ?i', $_POST['name'], $_POST['age']);
```

The data passed through the placeholders, screened by special screening function, depending on the type of filler. Ie you do not need to enter variables in the screening function type _mysqli_real_escape_string($value)_ or bring them to a numeric type _(int)$value_.

Details, see the file <a href="https://github.com/Vasiliy-Makogon/Database/blob/master/Mysql.php">/Mysql.php</a> or website <a href="http://www.database.phpinfo.su/">database.phpinfo.su</a>.
