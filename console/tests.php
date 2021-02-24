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



    // @todo...

    // Совершим ошибку - будет исключение типа MySqlException.
    $db->query('SELECTTTTTT ?i', 1);
} catch (MySqlException $e) {
    echo $e->getMessage() . PHP_EOL;
}