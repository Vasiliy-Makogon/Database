<?php
/**
 * @author Vasiliy Makogon, makogon-vs@yandex.ru
 * @link https://github.com/Vasiliy-Makogon/Database/
 *
 * Обёртка над объектом \mysqli_result.
 */

namespace Krugozor\Database\Mysql;

class Statement
{
    /**
     * Рузультат SQL-операции в виде объекта mysqli_result.
     *
     * @var \mysqli_result
     */
    private $mysqli_result = null;

    /**
     * @param mysqli_result
     */
    public function __construct(\mysqli_result $mysqli_result)
    {
        $this->mysqli_result = $mysqli_result;
    }

    /**
     * Извлекает результирующий ряд в виде ассоциативного массива.
     *
     * @see mysqli_fetch_assoc
     * @param void
     * @return array
     */

    public function fetchAssoc()
    {
        return mysqli_fetch_assoc($this->mysqli_result);
    }

    /**
     * Извлекает результирующий ряд в виде массива.
     *
     * @see mysqli_fetch_row
     * @param void
     * @return array
     */
    public function fetchRow()
    {
        return mysqli_fetch_row($this->mysqli_result);
    }

    /**
     * Извлекает результирующий ряд в виде объекта.
     *
     * @see mysqli_fetch_object
     * @return \stdClass
     */
    public function fetchObject()
    {
        return mysqli_fetch_object($this->mysqli_result);
    }

    /**
     * Возвращает результат в виде массива ассоциативных массивов.
     *
     * @return array
     */
    public function fetchAssocArray()
    {
        $array = array();

        while ($row = mysqli_fetch_assoc($this->mysqli_result)) {
            $array[] = $row;
        }

        return $array;
    }

    /**
     * Возвращает результат в виде массива массивов.
     *
     * @return array
     */
    public function fetchRowArray()
    {
        $array = array();

        while ($row = mysqli_fetch_row($this->mysqli_result)) {
            $array[] = $row;
        }

        return $array;
    }

    /**
     * Возвращает результат в виде массива объектов stdClass.
     *
     * @return array
     */
    public function fetchObjectArray()
    {
        $array = array();

        while ($row = mysqli_fetch_object($this->mysqli_result)) {
            $array[] = $row;
        }

        return $array;
    }

    /**
     * Возвращает значение первого поля результирующей таблицы.
     *
     * @return string
     */
    public function getOne()
    {
        $row = mysqli_fetch_row($this->mysqli_result);

        return $row[0];
    }

    /**
     * Возвращает количество рядов в результате.
     * Эта команда верна только для операторов SELECT.
     *
     * @see mysqli_num_rows
     * @return int
     */
    public function getNumRows()
    {
        return mysqli_num_rows($this->mysqli_result);
    }

    /**
     * Возвращает объект результата mysqli_result.
     *
     * @return \mysqli_result
     */
    public function getResult()
    {
        return $this->mysqli_result;
    }

    /**
     * Освобождает память занятую результатами запроса.
     *
     * @return void
     */
    public function free()
    {
        $this->mysqli_result->free();
    }

    public function __destruct()
    {
        $this->free();
    }
}
