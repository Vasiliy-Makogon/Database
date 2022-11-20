<?php

namespace Krugozor\Database;

/**
 * @author Vasiliy Makogon, makogon-vs@yandex.ru
 * @link https://github.com/Vasiliy-Makogon/Database/
 */
class Statement
{
    /**
     * @var \mysqli_result
     */
    protected \mysqli_result $mysqli_result;

    /**
     * @param \mysqli_result
     */
    public function __construct(\mysqli_result $mysqli_result)
    {
        $this->mysqli_result = $mysqli_result;
    }

    /**
     * Извлекает результирующий ряд в виде ассоциативного массива.
     *
     * @return array|null
     */
    public function fetchAssoc(): ?array
    {
        return $this->mysqli_result->fetch_assoc();
    }

    /**
     * Извлекает результирующий ряд в виде массива.
     *
     * @return array|null
     */
    public function fetchRow(): ?array
    {
        return $this->mysqli_result->fetch_row();
    }

    /**
     * Извлекает результирующий ряд в виде объекта.
     *
     * @return \stdClass|null
     */
    public function fetchObject(): ?\stdClass
    {
        return $this->mysqli_result->fetch_object();
    }

    /**
     * Возвращает результат в виде массива ассоциативных массивов.
     *
     * @return array
     */
    public function fetchAssocArray(): array
    {
        $array = array();

        while ($row = $this->mysqli_result->fetch_assoc()) {
            $array[] = $row;
        }

        return $array;
    }

    /**
     * Возвращает результат в виде массива массивов.
     *
     * @return array
     */
    public function fetchRowArray(): array
    {
        $array = array();

        while ($row = $this->mysqli_result->fetch_row()) {
            $array[] = $row;
        }

        return $array;
    }

    /**
     * Возвращает результат в виде массива объектов \stdClass.
     *
     * @return array
     */
    public function fetchObjectArray(): array
    {
        $array = array();

        while ($row = $this->mysqli_result->fetch_object()) {
            $array[] = $row;
        }

        return $array;
    }

    /**
     * Возвращает значение первого поля результирующей таблицы.
     *
     * @return string|null
     */
    public function getOne(): ?string
    {
        if ($row = $this->mysqli_result->fetch_row()) {
            return $row[0];
        }

        return null;
    }

    /**
     * Возвращает количество рядов в результате.
     * Эта команда верна только для операторов SELECT.
     *
     * @see mysqli_num_rows
     * @return int
     */
    public function getNumRows(): int
    {
        return $this->mysqli_result->num_rows;
    }

    /**
     * Возвращает объект результата \mysqli_result.
     *
     * @return \mysqli_result
     */
    public function getResult(): \mysqli_result
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
