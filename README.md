Rus
===

Что такое Database?
---

Database — библиотека классов на PHP 5.3 для простой, удобной, быстрой и безопасной работы с базой данных MySql, использующая расширение PHP mysqli.


Зачем в 2017 году нужен самописный класс для MySql, если в PHP есть абстракция PDO и расширение mysqli?
---

Основные недостатки всех библиотек для работы с базой в PHP это:

* **Многословность**
  * Что бы предотвратить <a href="http://ru.wikipedia.org/wiki/%D0%92%D0%BD%D0%B5%D0%B4%D1%80%D0%B5%D0%BD%D0%B8%D0%B5_SQL-%D0%BA%D0%BE%D0%B4%D0%B0">SQL-инъекции</a>, у разработчиков есть два пути:
    * Использовать <a href="http://php.net/manual/ru/mysqli.quickstart.prepared-statements.php">подготавливаемые запросы</a> (prepared statements).
    * Вручную экранировать параметры идущие в тело SQL-запроса. Строковые параметры прогонять через <a href="http://php.net/manual/ru/mysqli.real-escape-string.php">mysqli_real_escape_string()</a>, а ожидаемые числовые параметры приводить к соответствующим типам — int и float.
  * Оба подхода имеют колоссальные недостатки:
    * Подготавливаемые запросы <a target="_blank" rel="nofollow" href="http://php.net/manual/ru/mysqli.prepare.php#refsect1-mysqli.prepare-examples">ужасно многословны</a>. Пользоваться "из коробки" абстракцией PDO или расширением mysqli, без агрегирования всех методов для получения данных из СУБД просто невозможно — что бы получить значение из таблицы необходимо написать минимум 5 строк кода! И так на каждый запрос!
    * Экранирование вручную параметров, идущих в тело SQL-запроса — даже не обсуждается. Хороший программист — ленивый программист. Всё должно быть максимально автоматизировано.
* **Невозможность получить SQL запрос для отладки**


Решение: Database — класс для работы с MySql
---
1. Избавляет от многословности — вместо 3 и более строк кода для исполнения одного запроса при использовании "родной" библиотеки, вы пишите всего 1!
2. Экранирует все параметры, идущие в тело запроса, согласно указанному типу заполнителей — надежная защита от SQL-инъекций.
3. Не замещает функциональность "родного" mysqli адаптера, а просто дополняет его.


Что такое placeholders (заполнители)?
---

Placeholders (англ. — заполнители) — специальные типизированные маркеры, которые пишутся в строке SQL запроса вместо явных значений (параметров запроса). А сами значения передаются "позже", в качестве последующих аргументов основного метода, выполняющего SQL-запрос:

```php
<?php
// Соединение с СУБД и получение объекта Database_Mysql
// Database_Mysql - "обертка" над "родным" объектом mysqli
$db = Database_Mysql::create("localhost", "root", "password")
      // Выбор базы данных
      ->setDatabaseName("test")
      // Выбор кодировки
      ->setCharset("utf8");

// Получение объекта результата Database_Mysql_Statement
// Database_Mysql_Statement - "обертка" над "родным" объектом mysqli_result
$result = $db->query("SELECT * FROM `users` WHERE `name` = '?s' AND `age` = ?i", "Василий", 30);

// Получаем данные (в виде ассоциативного массива, например)
$data = $result->fetch_assoc();

// Не работает запрос? Не проблема - выведите его на печать:
echo $db->getQueryString();
```

Eng
===

Database - class for develop with database mysql, use adapter PHP mysqli and use placeholder, when each literal (argument) in string SQL query escaping without specific PHP functions like a mysqli_real_escape_string(). 

```php
$db->query('SELECT * FROM `t` WHERE `name` = "?s" AND `age` = ?i', $_POST['name'], $_POST['age']);
```

The data passed through the placeholders, screened by special screening function, depending on the type of filler. Ie you do not need to enter variables in the screening function type _mysqli_real_escape_string($value)_ or bring them to a numeric type _(int)$value_.

Details, see the file <a href="https://github.com/Vasiliy-Makogon/Database/blob/master/Mysql.php">/Mysql.php</a> or website <a href="http://www.database.phpinfo.su/">database.phpinfo.su</a>.
