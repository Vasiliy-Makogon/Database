Rus
---

Класс для простой работы с СУБД MySql с использованием расширения PHP mysqli.

Данный класс использует технологию placeholders - для формирования корректных SQL-запросов, в строке запроса вместо значений пишутся специальные типизированные маркеры - заполнители, а сами данные передаются "позже", в качестве последующих аргументов основного метода, выполняющего SQL-запрос:
<code>
```sql
$db->query('SELECT * FROM `t` WHERE `name` = "?s" AND `age` = ?i', $_POST['name'], $_POST['age']);
```
</code>

Данные, прошедшие через систему placeholders, экранируются специальными функциями экранирования, в зависимости от типа заполнителей. Т.е. вам нет необходимости заключать переменные в функции экранирования типа _mysqli_real_escape_string($value)_ или приводить их к числовому типу через _(int)$value_.

Подробное описание см. в файле <a href="https://github.com/Vasiliy-Makogon/Database/blob/master/Mysql.php">/Mysql.php</a>.
Тесты и различные вариации использования см. в файле <a href="https://github.com/Vasiliy-Makogon/Database/blob/master/samples.php">./samples.php</a>.

Eng
---

Database - class for develop with database mysql, use adapter PHP mysqli and use placeholder, when each literal (argument) in string SQL query escaping without specific PHP functions like a mysqli_real_escape_string(). 
<code>
```sql
$db->query('SELECT * FROM `t` WHERE `name` = "?s" AND `age` = ?i', $_POST['name'], $_POST['age']);
```
</code>

The data passed through the placeholders, screened by special screening function, depending on the type of filler. Ie you do not need to enter variables in the screening function type mysqli_real_escape_string ($ value) or bring them to a numeric type (int) $ value.

Details, see the file <a href="https://github.com/Vasiliy-Makogon/Database/blob/master/Mysql.php">/Mysql.php</a>. Tests and the use of different variations, see the file <a href="https://github.com/Vasiliy-Makogon/Database/blob/master/samples.php">./samples.php</a>.
