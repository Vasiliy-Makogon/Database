<?php
//header('Content-type: text/plain; charset=utf-8');
header('Content-type: text/plain; charset=windows-1251');

error_reporting(E_ALL|E_STRICT);

include('../Mysql.php');
include('Exception.php');
include('Statement.php');

try
{
    $db = Krugozor_Database_Mysql::create('localhost', 'root', '')
          ->setCharset('cp1251')
          ->setDatabaseName('test');

    $db->query('DROP TABLE IF EXISTS ?f', 'test');
    echo $db->getQueryString() . "\n\n";

    $db->query('CREATE TABLE test(
    id int unsigned not null primary key auto_increment,
    name varchar(255),
    age tinyint,
    adress varchar(255)
    )');

    $db->query('INSERT INTO `test` VALUES (?n, "?s", "?s", "?s")', null, 'Иван', '25', 'Москва, ул. Ленина, ЗАО "Рога и копыта"');
    getAffectedInfo($db);

    $user = array('name' => 'Василий', 'age' => '30', 'adress' => 'Москва, ул. Деловая, 20');
    $db->query('INSERT INTO `test` SET ?As', $user);
    getAffectedInfo($db);

    $user = array('id' => null, 'name' => 'Пётр', 'age' => '19', 'adress' => 'Москва, ул. Красносельская, 2А');
    $db->query('INSERT INTO `test` SET ?A[?n, "?s", "?s", "?s"]', $user);
    getAffectedInfo($db);

    $user = array('id' => null, 'name' => '%Настя%', 'age' => '17', 'adress' => 'Москва, ул. Радиальная, 12');
    $db->query('INSERT INTO `test` VALUES (?a[?n, "?s", "?s", "?s"])', $user);
    getAffectedInfo($db);

    // LIKE-поиск записи, содержащей в поле `name` служебный символ % (процент)
    $result = $db->query('SELECT * FROM `test` WHERE `name` LIKE "%?S%"', '%');
    getSelectInfo($db, $result);

    $result = $db->query('SELECT * FROM `test` WHERE `id` = ?i', 1);
    getSelectInfo($db, $result);

    // Выбор записи по маркеру числа - ?i, но с указанием не числовой строки '2+мусор'.
    $result = $db->query('SELECT * FROM `test` WHERE `id` = ?i', '2+мусор');
    getSelectInfo($db, $result);

    // Передать массив и получить результат на основе выборки.
    $result = $db->query('SELECT * FROM `test` WHERE `name` IN (?a["?s", "?s", "?s"])', array('Катя', 'Иван', 'Роман'));
    getSelectInfo($db, $result);

    // Тоже самое, но типизировать и перечислять в заменителях точное количество аргументов не нужно.
    // Значения аргументов будут заключены в "двойные" кавчки.
    $result = $db->query('SELECT * FROM `test` WHERE `name` IN (?as) OR `name` IN (?as)',
                         array('Пётр', 'Маша', 'Роман', 'Ибрагим'),
                         array('Иван', 'Фёдор', 'Катя')
                        );
    getSelectInfo($db, $result);

    // Записать NULL в качестве значений
    $db->query('INSERT INTO `test` VALUES (?n, ?n, ?n, ?n)', NULL, NULL, NULL, NULL);
    getSelectInfo($db, $result);

    // Применение метода queryArguments()
    $sql = 'SELECT * FROM `test` WHERE `name` IN (?as)';
    $arguments[] = array('Пётр', 'Маша', 'Роман');
    $sql .= ' OR `name` IN (?as)';
    $arguments[] = array('Пётр', 'Иван', 'Катя');
    $result = $db->queryArguments($sql, $arguments);
    getSelectInfo($db, $result);

    // Получаем все запросы текущего соединения:
    print_r($db->getQueries());
    echo "\n\n";

    // получить все и вывести
    $res = $db->query('SELECT * FROM test');
    while ($data = $res->fetch_assoc()) {
        print_r($data);
        echo "\n";
    }
    echo "\n\n";

    // Всё удалим
    $db->query('DELETE FROM `test`');
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
    echo "SQL: " . $db->getQueryString();
    echo "\n";
    echo 'Получено записей: ' . $result->getNumRows();
    echo "\n\n";
}