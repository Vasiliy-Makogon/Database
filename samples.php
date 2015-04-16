<?php
// Примеры использования библиоткеки.
error_reporting(E_ALL|E_STRICT);
header('Content-type: text/plain; charset=utf-8');

include('./Mysql.php');
include('./Mysql/Exception.php');
include('./Mysql/Statement.php');

try
{
    $db = Krugozor_Database_Mysql::create('localhost', 'root', '')
          ->setCharset('utf8')
          ->setDatabaseName('test');

    // Создаем таблицу для тестирования.
    $db->query('DROP TABLE IF EXISTS users');

    $db->query('
        CREATE TABLE users(
            id int unsigned not null primary key auto_increment,
            name varchar(255),
            age tinyint,
            adress varchar(255)
        )
    ');

    // Ради интереса раскоментируйте строку ниже и посмотрите на поведение режима MODE_STRICT на разных запросах
    // $db->setTypeMode(Krugozor_Database_Mysql::MODE_STRICT);


    // Основное: заполнители, преобразования и экранирование аргументов:
    // Для наглядности с помощью метода prepare() иллюстрируем поведение заполнителей на разных типах данных

    // Преобразования данных в integer (заполнитель ?i)
    $data = array(1, '2', '3+мусор', true, null);
    pr($data);
    result($db->prepare('?ai', $data));

    // Преобразования данных во float (заполнитель ?p)
    $data = array(1, 2.2, '3.3', '4.4+мусор', true, null);
    pr($data);
    result($db->prepare('?ap', $data));

    // Преобразования данных в string (заполнитель ?s)
    $data = array("\n", "\r", "'", '"', true, null);
    pr($data);
    result( $db->prepare('?as', $data) );

    // Преобразования данных в string для LIKE-поиска(заполнитель ?S)
    $data = "%_";
    pr($data);
    result( $db->prepare('?S', $data) );



    // Различные варианты INSERT:

    $db->query('INSERT INTO `users` VALUES (?n, "?s", "?i", "?s")', null, 'Иван', '25', 'г. Клин, ЗАО "Рога и копыта"');
    getAffectedInfo($db);

    $user = array('name' => 'Василий', 'age' => '30', 'adress' => "Москва, ОАО 'М.Видео'");
    $db->query('INSERT INTO `users` SET ?As', $user);
    getAffectedInfo($db);

    $user = array('id' => null, 'name' => 'Пётр', 'age' => '19', 'adress' => 'Москва, ул. Красносельская, 40\12');
    $db->query('INSERT INTO `users` SET ?A[?n, "?s", "?s", "?s"]', $user);
    getAffectedInfo($db);

    $user = array('id' => null, 'name' => 'Анна_Каренина', 'age' => '23', 'adress' => 'Москва, ул. Радиальная, 12');
    $db->query('INSERT INTO `users` VALUES (?a[?n, "?s", "?i", "?s"])', $user);
    getAffectedInfo($db);


    // Различные варианты SELECT:

    $result = $db->query('SELECT * FROM `users` WHERE `id` = ?i', '1');
    getSelectInfo($db, $result);

    // Выбор записи по маркеру числа - ?i, но с указанием не числовой строки '2+мусор'
    $result = $db->query('SELECT * FROM `users` WHERE `id` = ?i', '2+мусор');
    getSelectInfo($db, $result);

    $result = $db->query('SELECT * FROM `users` WHERE `name` IN (?a["?s", "?s", "?s"])', array('Василий', 'Иван', 'Анна_Каренина'));
    getSelectInfo($db, $result);

    $result = $db->query(
        'SELECT * FROM `users` WHERE `name` IN (?as) OR `id` IN (?ai)',
        array('Пётр', 'Маша', 'Роман', 'Василий'),
        array('2', 3, 04)
    );
    getSelectInfo($db, $result);

    // LIKE-поиск записи, содержащей в поле `name` служебный символ `_`
    $result = $db->query('SELECT * FROM `users` WHERE `name` LIKE "%?S%"', '_');
    getSelectInfo($db, $result);

    // Применение метода queryArguments()
    $sql = 'SELECT * FROM `users` WHERE `name` IN (?as) OR `name` IN (?as)';
    $arguments[] = array('Пётр', 'Маша', 'Роман');
    $arguments[] = array('Пётр', 'Иван', 'Катя');
    $result = $db->queryArguments($sql, $arguments);
    getSelectInfo($db, $result);

    // Получить все и вывести
    $res = $db->query('SELECT * FROM users');
    while ($data = $res->fetch_assoc()) {
        print_r($data);
        echo "\n";
    }


    // Прочее:

    // Записать NULL в качестве значений, аргументы - игнорируются
    $db->query('INSERT INTO `users` VALUES (?n, ?n, ?n, ?n)', 1, 'string', 3.5, true);
    getAffectedInfo($db, $result);

    // Получаем все запросы текущего соединения:
    print_r($db->getQueries());
    echo "\n";

    // Всё удалим. Имя таблицы передается как аргумент.
    $db->query('DELETE FROM ?f', 'users');
    getAffectedInfo($db);
}
catch (Krugozor_Database_Mysql_Exception $e)
{
    echo $e->getMessage();
}

/**
 * Просмотр информации после INSERT, UPDATE или DELETE.
 *
 * @param $db Krugozor_Database_Mysql
 */
function getAffectedInfo($db)
{
    echo "Original query: " . $db->getOriginalQueryString();
    echo "\n";
    echo "SQL: " . $db->getQueryString();
    echo "\n";
    echo 'Затронуто строк: ' . $db->getAffectedRows();
    if ($id = $db->getLastInsertId()) {
        echo "\n";
        echo 'Last insert ID: ' . $db->getLastInsertId();
    }
    echo "\n\n";
}

/**
 * Просмотр информации после SELECT.
 *
 * @param $db Krugozor_Database_Mysql
 * @param $result Krugozor_Database_Mysql_Statement
 */
function getSelectInfo($db, $result)
{
    echo "Original query: " . $db->getOriginalQueryString();
    echo "\n";
    echo "SQL: " . $db->getQueryString();
    echo "\n";
    echo 'Получено записей: ' . $result->getNumRows();
    echo "\n\n";
}

function pr($data)
{
    echo "Исходные данные: " . print_r($data, true) . "\n";
}

function result($string)
{
    echo "Результат: $string\n\n";
}