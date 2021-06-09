<?php
foreach (glob(dirname(dirname(__FILE__)) . "/src/*.php") as $filename) {
    require_once $filename;
}

use Krugozor\Database\Mysql;
use Krugozor\Database\Statement;
use Krugozor\Database\MySqlException;

$db = Mysql::create('localhost', 'root', '');

try {
    // Наглядный пример двух режимов работы и приведения типов аргументов к типу заполнителя:
    // Результат: 8
    echo $db->query('SELECT ?i + ?i', 3, 5)->getOne() . PHP_EOL;
    // Результат: 8, т.к. значение 3.5 было приведено к типу int,
    // поскольку по-умолчанию активирован режим Mysql::MODE_TRANSFORM
    echo $db->query('SELECT ?i + ?i', 3.5, 5)->getOne() . PHP_EOL;
    // Активируем строгий режим типизации.
    $db->setTypeMode(Mysql::MODE_STRICT);
    // Результат: 8.5
    echo $db->query('SELECT ?d + ?i', 3.5, 5)->getOne() . PHP_EOL;
    // Результат: 8.5, строки, как и числа, интерпретируются библиотекой правильно.
    echo $db->query('SELECT ?d + ?i', '3.5', '5')->getOne() . PHP_EOL;


    // Выбор БД
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

    // Вставка данных раздичными методами:

    // Простая вставка данных через заполнители разных типов:
    $db->query("INSERT INTO `test` VALUES (null, '?s', ?i)", 'Иоанн Грозный', '54');

    // Вставка значений через заполнитель ассоциативного множества типа string:
    $data = array('name' => "Д'Артаньян", 'age' => '19');
    $db->query('INSERT INTO `test` SET ?As', $data);

    // Вставка значений через заполнитель ассоциативного множества с явным
    // указанием типа и количества аргументов:
    $data = array('name' => "Иосиф Сталин", 'age' => '56');
    $db->query('INSERT INTO `test` SET ?A["?s", ?i]', $data);

    // @todo...

    // Совершим ошибку - будет исключение типа MySqlException.
    // $db->query('SELECTTTTTT ?i', 1);
} catch (MySqlException $e) {
    echo $e->getMessage() . PHP_EOL;
}