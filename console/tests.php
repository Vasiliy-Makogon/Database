<?php
foreach (glob(dirname(dirname(__FILE__)) . "/src/*.php") as $filename) {
    require_once $filename;
}

// Это файл для понимания сути работы. Вы можете запускать его много раз из консоли.
// Не забудьте создать базу с именеем `test`.

use Krugozor\Database\Mysql;
use Krugozor\Database\Statement;
use Krugozor\Database\MySqlException;

$db = Mysql::create('localhost', 'root', '');

try {
    // Наглядный пример двух режимов работы библиотеки:

    // 1. Режим Mysql::MODE_TRANSFORM

    // Результат: 8 - простое сложение двух integer
    $result = $db->query('SELECT ?i + ?i', 3, 5);
    echo "{$db->getQueryString()} ({$result->getOne()})" .  PHP_EOL;

    // Результат: так же 8, т.к. значение 3.5 было приведено к типу int,
    // поскольку по-умолчанию активирован режим Mysql::MODE_TRANSFORM
    // и значение 3.5 было приведено к типу int посредством самого языка php
    $result = $db->query('SELECT ?i + ?i', 3.5, 5);
    echo "{$db->getQueryString()} ({$result->getOne()})" .  PHP_EOL;

    // Результат: 1, т.к. значение null приведено к 0, true - к 1
    $result = $db->query('SELECT ?i + ?i', null, true);
    echo "{$db->getQueryString()} ({$result->getOne()})" .  PHP_EOL;

    $result = $db->query('SELECT "?s", "?s", "?s"', null, false, 0.001);
    echo "{$db->getQueryString()} ({$result->getOne()})" .  PHP_EOL;

    // Активируем строгий режим типизации.
    $db->setTypeMode(Mysql::MODE_STRICT);

    // Результат: 8.5
    $result = $db->query('SELECT ?d + ?i', 3.5, 5);
    echo "{$db->getQueryString()} ({$result->getOne()})" .  PHP_EOL;

    // Результат: 8.5, не смотря на режим Mysql::MODE_STRICT, строки и числа в аргументах
    // интерпретируются библиотекой правильно, так как они представляют собой числа с плавающей точкой
    $result = $db->query('SELECT ?d + ?i', '3.5', '5');
    echo "{$db->getQueryString()} ({$result->getOne()})" .  PHP_EOL;

    // А в строгом режиме уже такие фокусы не пройдут:
    // $db->query('SELECT "?s"', false);
    // будет выброшено исключение:
    // `Попытка указать для заполнителя типа string значение типа boolean в шаблоне запроса SELECT "?s"`

    // Примеры со вставкой и выборкой:

    // Выбор БД для работы.
    $db->setDatabaseName('test');

    // Создание таблицы
    $db->query('
        CREATE TABLE IF NOT EXISTS test (
            id int unsigned not null primary key auto_increment,
            name varchar(50) not null,
            age int not null
        );
    ');
    // Очистим её, т.к. возможно вы будете запускать этот скрипт неоднократно.
    $db->query('TRUNCATE TABLE `test`;');
    // Вернем нормальный режим работы, строгий режим я сам не знаю для чего сделал :)
    $db->setTypeMode(Mysql::MODE_TRANSFORM);

    // Вставка данных раздичными методами:

    // Простая вставка данных через заполнители разных типов:
    $db->query("INSERT INTO `test` VALUES (null, '?s', ?i)", 'Иоанн Грозный', '54');
    echo "{$db->getQueryString()} (вставлено рядов: {$db->getAffectedRows()})" .  PHP_EOL;

    // Вставка значений через заполнитель ассоциативного множества типа string:
    $data = array('name' => "Д'Артаньян", 'age' => '19');
    $db->query('INSERT INTO `test` SET ?As', $data);
    echo "{$db->getQueryString()} (вставлено рядов: {$db->getAffectedRows()})" .  PHP_EOL;

    // Вставка значений через заполнитель ассоциативного множества с явным
    // указанием типа и количества аргументов:
    $data = array('name' => "%%% Иосиф Сталин %%%", 'age' => '56');
    $db->query('INSERT INTO `test` SET ?A["?s", ?i]', $data);
    echo "{$db->getQueryString()} (вставлено рядов: {$db->getAffectedRows()})" .  PHP_EOL;

    // Выборки:

    // Обычная выборка:
    $result = $db->query('SELECT `name` FROM `test` WHERE `name` = "?s"', "Д'Артаньян");
    echo "{$db->getQueryString()} ({$result->getOne()})" .  PHP_EOL;

    // Обычная выборка, но имя полей и базы_данных.таблицы так же передаем через заполнитель:
    $result = $db->query('SELECT ?f FROM ?f WHERE ?f = "?s"', 'name', 'test.test', 'id', 1);
    echo "{$db->getQueryString()} ({$result->getOne()})" .  PHP_EOL;

    // LIKE-поиск. Обратите внимание, что в имени Сталина присутствует спецсимвол % -
    // он будет корректно заэкранирован:
    $result = $db->query('SELECT `name` FROM `test` WHERE `name` LIKE "%?S%"', "%");
    echo "{$db->getQueryString()} ({$result->getOne()})" .  PHP_EOL;

    // Заполнитель null-типа игнорирует аргумент:
    $result = $db->query('SELECT ?n', 123);
    echo "{$db->getQueryString()} ({$result->getOne()})" .  PHP_EOL;

    // Применение метода queryArguments() - аргументы передаются в виде массива.
    //Это второй, после метода query(), метод запросов в базу:
    $sql = 'SELECT * FROM `test` WHERE `name` like "%?s%" OR `name` = "?s"';
    $arguments[] = "Сталин";
    $arguments[] = "Д'Артаньян";
    $result = $db->queryArguments($sql, $arguments);
    // Получим количество рядов в результате
    echo "{$db->getQueryString()} (получено рядов: {$result->getNumRows()})" .  PHP_EOL;

    // Совершим ошибку - будет исключение типа MySqlException.
    $db->query('SELECTTTTTT ?i', 1);
} catch (MySqlException $e) {
    echo $e->getMessage() . PHP_EOL;
}