<?php

namespace Krugozor\Database;

/**
 * @author Vasiliy Makogon, makogon-vs@yandex.ru
 * @link https://github.com/Vasiliy-Makogon/Database/
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *     Библиотека для удобной и безопасной работы с СУБД MySql на базе расширения PHP mysqli.
 * ---------------------------------------------------------------------------------------------------------------------
 *
 * Библиотека имулирует технологию Prepared statement (или placeholders) - для формирования корректных SQL-запросов
 * (т.е. запросов, исключающих SQL-уязвимости), в строке запроса вместо значений пишутся специальные типизированные
 * маркеры - т.н. "заполнители", а сами данные передаются "позже", в качестве последующих аргументов основного метода,
 * выполняющего SQL-запрос - Mysql::query($sql [, $arg, $...]):
 *
 *     $db->query('SELECT * FROM `table` WHERE `name` = "?s" AND `age` = ?i', $_POST['name'], $_POST['age']);
 *
 * Аргументы SQL-запроса, прошедшие через систему placeholders данного класса, экранируются специальными функциями
 * экранирования, в зависимости от типа заполнителей. Т.е. вам теперь нет необходимости заключать переменные в функции
 * экранирования типа mysqli_real_escape_string($value) или приводить их к числовому типу через (int)$value.
 *
 * Кроме того, данный класс позволяет:
 * - получать "подготовленный" SQL-запрос для отладки, т.е. запрос с реальными значениями, что невозможно сделать
 * используя "сырые" драйверы PHP типа PDO.
 * - получать список всех запросов выполненных в рамках одного подключения к Mysql-серверу.
 *
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *    Режимы работы.
 * ---------------------------------------------------------------------------------------------------------------------
 *
 * Существует два режима работы класса:
 * Mysql::MODE_STRICT    - строгий режим соответствия типа заполнителя и типа аргумента.
 * Mysql::MODE_TRANSFORM - режим преобразования аргумента к типу заполнителя при несовпадении
 *                                           типа заполнителя и типа аргумента.
 *
 * Режим Mysql::MODE_TRANSFORM установлен по умолчанию и является основным для большинства приложений.
 * Если же вам нужна максимальная прозрачность операций над типами данных, производимых библиотекой Datavase,
 * установите режим Mysql::MODE_STRICT.
 *
 *
 *     MODE_STRICT
 *
 * В "строгом" режиме MODE_STRICT аргументы, передаваемые в основной метод Mysql::query(),
 * должны соответствовать типу заполнителя.
 * Например, попытка передать в качестве аргумента значение 55.5 или '55.5' для заполнителя целочисленного типа ?i
 * приведет к выбросу исключения:
 *
 * $db->setTypeMode(Mysql::MODE_STRICT); // устанавливаем строгий режим работы
 * $db->query('SELECT ?i', 55.5); // Попытка указать для заполнителя типа int значение типа double в шаблоне запроса SELECT ?i
 *
 * Это утверждение не относится к числам (целым и с плавающей точкой), заключенным в строки.
 * С точки зрения библиотеки, строка '123' и значение 123 являются типом int.
 *
 *
 *     MODE_TRANSFORM
 *
 * Режим MODE_TRANSFORM является "щадящим" режимом и при несоответствии типа заполнителя и типа аргумента не генерирует
 * исключение, а пытается преобразовать аргумент к нужному типу заполнителя посредством самого языка PHP.
 *
 * Допускаются следующие преобразования:
 *
 * К типу int приводятся (заполнитель ?i):
 *     - числа с плавающей точкой, представленные как строка или тип double
 *     - bool
 *     - null
 *
 * К типу double приводятся (заполнитель ?d):
 *     - целые числа, представленные как строка или тип int
 *     - bool
 *     - null
 *
 * К типу string приводятся (заполнитель ?s):
 *     - значение boolean TRUE преобразуется в строку "1", а значение FALSE преобразуется в "" (пустую строку).
 *     - значение типа numeric преобразуется в строку согласно правилам преобразования, определенным языком.
 *     - NULL преобразуется в пустую строку.
 *
 * К типу null приводятся (заполнитель ?n):
 *     - любые аргументы
 *
 * Для массивов, объектов и ресурсов преобразования не допускаются.
 *
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *    Типы маркеров-заполнителей
 * ---------------------------------------------------------------------------------------------------------------------
 *
 * ?f - заполнитель имени таблицы или поля (первая буква слова field).
 *      Данный заполнитель предназначен для случаев, когда имя таблицы или поля передается в запроос через аргумент.
 *
 * ?i - заполнитель целого числа (первая буква слова integer).
 *      В режиме MODE_TRANSFORM любые скалярные аргументы принудительно приводятся к типу integer
 *      согласно правилам преобразования к типу integer в PHP.
 *
 * ?d - заполнитель числа с плавающей точкой (первая буква слова double).
 *      В режиме MODE_TRANSFORM любые скалярные аргументы принудительно приводятся к типу float
 *      согласно правилам преобразования к типу float в PHP.
 *
 * ?s - заполнитель строкового типа (первая буква слова string).
 *      В режиме MODE_TRANSFORM любые скалярные аргументы принудительно приводятся к типу string
 *      согласно правилам преобразования к типу string в PHP
 *      и экранируются с помощью функции PHP mysqli_real_escape_string().
 *
 * ?S - заполнитель строкового типа для подстановки в SQL-оператор LIKE (первая буква слова string).
 *      В режиме MODE_TRANSFORM Любые скалярные аргументы принудительно приводятся к типу string
 *      согласно правилам преобразования к типу string в PHP
 *      и экранируются с помощью функции PHP mysqli_real_escape_string() + экранирование спецсимволов,
 *      используемых в операторе LIKE (%_).
 *
 * ?n - заполнитель NULL типа (первая буква слова null).
 *      В режиме MODE_TRANSFORM любые аргументы игнорируются, заполнители заменяются на строку `NULL` в SQL запросе.
 *
 * ?A* - заполнитель ассоциативного множества для ассоциативного массива-аргумента, генерирующий последовательность
 *       пар ключ => значение.
 *       Пример: "key_1" = "val_1", "key_2" = "val_2", ...
 *
 * ?a* - заполнитель множества из простого (или также ассоциативного) массива-аргумента, генерирующий последовательность
 *       значений.
 *       Пример: "val_1", "val_2", ...
 *
 *       где * после маркера заполнителя - один из типов:
 *       - i (int)
 *       - p (float)
 *       - s (string)
 *       правила преобразования и экранирования такие же, как и для одиночных скалярных аргументов (см. выше).
 *
 * ?A[?n, ?s, ?i, ?d] - заполнитель ассоциативного множества с явным указанием типа и количества аргументов,
 *                      генерирующий последовательность пар ключ => значение.
 *                      Пример: "key_1" = "val_1", "key_2" => "val_2", ...
 *
 * ?a[?n, ?s, ?i, ?d] - заполнитель множества с явным указанием типа и количества аргументов, генерирующий
 *                      последовательность значений.
 *                      Пример: "val_1", "val_2", ...
 *
 *
 * ---------------------------------------------------------------------------------------------------------------------
 *    Ограничивающие кавчки
 * ---------------------------------------------------------------------------------------------------------------------
 *
 * Данный класс при формировании SQL-запроса НЕ занимается проставлением ограничивающих кавычек для одиночных
 * заполнителей скалярного типа, таких как ?i, ?d и ?s. Это сделано по идеологическим соображениям,
 * автоподстановка кавычек может стать ограничением для возможностей SQL.
 * Например, выражение
 *     $db->query('SELECT "Total: ?s"', '200');
 * вернёт строку
 *     'Total: 200'
 * Если бы кавычки, ограничивающие строковой литерал, ставились бы автоматически,
 * то вышеприведённое условие вернуло бы строку
 *     'Total: "200"'
 * что было бы не ожидаемым поведением.
 *
 * Тем не менее, для перечислений ?as, ?ai, ?ap, ?As, ?Ai и ?Ap ограничивающие кавычки ставятся принудительно, т.к.
 * перечисления всегда используются в запросах, где наличие кавчек обязательно или не играет роли (а так ли это?):
 *
 *    $db->query('INSERT INTO `test` SET ?As', array('name' => 'Маша', 'age' => '23', 'adress' => 'Москва'));
 *    -> INSERT INTO test SET `name` = "Маша", `age` = "23", `adress` = "Москва"
 *
 *    $db->query('SELECT * FROM table WHERE field IN (?as)', array('55', '12', '132'));
 *    -> SELECT * FROM table WHERE field IN ("55", "12", "132")
 *
 * Также исключения составляют заполнители типа ?f, предназначенные для передачи в запрос имен таблиц и полей.
 * Аргумент заполнителя ?f всегда обрамляется обратными кавычками (`):
 *
 *    $db->query('SELECT ?f FROM ?f', 'my_field', 'my_table');
 *    -> SELECT `my_field` FROM `my_table`
 */
class Mysql
{
    /**
     * Строгий режим типизации.
     * Если тип заполнителя не совпадает с типом аргумента, то будет выброшено исключение.
     * Пример такой ситуации:
     *
     * $db->query('SELECT * FROM `table` WHERE `id` = ?i', '2+мусор');
     *
     * - в данной ситуации тип заполнителя ?i - число или числовая строка,
     *   а в качестве аргумента передаётся строка '2+мусор' не являющаяся ни числом, ни числовой строкой.
     *
     * @var int
     */
    const MODE_STRICT = 1;

    /**
     * Режим преобразования.
     * Если тип заполнителя не совпадает с типом аргумента, аргумент принудительно будет приведён
     * к нужному типу - к типу заполнителя.
     * Пример такой ситуации:
     *
     * $db->query('SELECT * FROM `table` WHERE `id` = ?i', '2+мусор');
     *
     * - в данной ситуации тип заполнителя ?i - число или числовая строка,
     *   а в качестве аргумента передаётся строка '2+мусор' не являющаяся ни числом, ни числовой строкой.
     *   Строка '2+мусор' будет принудительно приведена к типу int согласно правилам преобразования типов в PHP.
     *
     * @var int
     */
    const MODE_TRANSFORM = 2;

    /**
     * Режим работы инстанцированного объекта.
     * См. описание констант self::MODE_STRICT и self::MODE_TRANSFORM.
     *
     * @var int
     */
    protected $type_mode = self::MODE_TRANSFORM;

    /**
     * @var string
     */
    protected $server;

    /**
     * @var string
     */
    protected $user;

    /**
     * @var string
     */
    protected $password;

    /**
     * @var string
     */
    protected $port;

    /**
     * @var string
     */
    protected $socket;

    /**
     * Имя текущей БД.
     *
     * @var string
     */
    protected $database_name;

    /**
     * Стандартный объект соединения сервером \mysqli.
     *
     * @var \mysqli
     */
    protected $mysqli;

    /**
     * Строка последнего SQL-запроса до преобразования.
     *
     * @var string
     */
    private $original_query;

    /**
     * Строка последнего SQL-запроса после преобразования.
     *
     * @var string
     */
    private $query;

    /**
     * Массив со всеми запросами, которые были выполнены объектом.
     * Ключи - SQL после преобразования, значения - SQL до преобразования.
     *
     * @var array
     */
    private $queries = array();

    /**
     * Накапливать ли в хранилище $this->queries исполненные запросы.
     *
     * @var bool
     */
    private $store_queries = true;

    /**
     * Создает инстанс данного класса.
     *
     * @param string $server имя сервера
     * @param string $username имя пользователя
     * @param string $password пароль
     * @param string $port порт
     * @param string $socket сокет
     */
    public static function create($server, $username, $password, $port = null, $socket = null)
    {
        return new self($server, $username, $password, $port, $socket);
    }

    /**
     * Задает набор символов по умолчанию.
     * Вызов данного метода эквивалентен следующей установки конфигурации MySql-сервера:
     * SET character_set_client = charset_name;
     * SET character_set_results = charset_name;
     * SET character_set_connection = charset_name;
     *
     * @param string $charset
     * @return Mysql
     */
    public function setCharset($charset)
    {
        if (!$this->mysqli->set_charset($charset)) {
            throw new MySqlException(__METHOD__ . ': ' . $this->mysqli->error);
        }

        return $this;
    }

    /**
     * Возвращает кодировку по умолчанию, установленную для соединения с БД.
     *
     * @param void
     * @return string
     */
    public function getCharset()
    {
        return $this->mysqli->character_set_name();
    }

    /**
     * Устанавливает имя используемой СУБД.
     *
     * @param string имя базы данных
     * @return Mysql
     */
    public function setDatabaseName($database_name)
    {
        if (!$database_name) {
            throw new MySqlException(__METHOD__ . ': Не указано имя базы данных');
        }

        $this->database_name = $database_name;

        if (!$this->mysqli->select_db($this->database_name)) {
            throw new MySqlException(__METHOD__ . ': ' . $this->mysqli->error);
        }

        return $this;
    }

    /**
     * Возвращает имя текущей БД.
     *
     * @return string
     */
    public function getDatabaseName()
    {
        return $this->database_name;
    }

    /**
     * Устанавливает режим поведения при несовпадении типа заполнителя и типа аргумента.
     *
     * @param $value int|string
     * @return Mysql
     */
    public function setTypeMode($value)
    {
        if (!in_array((int)$value, array(self::MODE_STRICT, self::MODE_TRANSFORM))) {
            throw new MySqlException(__METHOD__ . ': Указан неизвестный тип режима');
        }

        $this->type_mode = $value;

        return $this;
    }

    /**
     * Устанавливает свойство $this->store_queries, отвечающее за накопление
     * исполненных запросов в хранилище $this->queries.
     *
     * @param bool $value
     * @return Mysql
     */
    public function setStoreQueries($value)
    {
        $this->store_queries = (bool) $value;

        return $this;
    }

    /**
     * Выполняет SQL-запрос.
     * Принимает обязательный параметр - SQL-запрос и, в случае наличия,
     * любое количество аргументов - значения заполнителей.
     *
     * @param string строка SQL-запроса
     * @param mixed аргументы для заполнителей
     * @return bool|Statement false в случае ошибки, в обратном случае объект результата Statement
     * @throws Exception
     */
    public function query()
    {
        if (!func_num_args()) {
            return false;
        }

        $args = func_get_args();

        $query = $this->original_query = array_shift($args);

        $this->query = $this->parse($query, $args);

        $result = $this->mysqli->query($this->query);

        if ($this->store_queries) {
            $this->queries[$this->query] = $this->original_query;
        }

        if ($result === false) {
            throw new MySqlException(__METHOD__ . ': ' . $this->mysqli->error . '; SQL: ' . $this->query);
        }

        if (is_object($result) && $result instanceof \mysqli_result) {
            return new Statement($result);
        }

        return $result;
    }

    /**
     * Поведение аналогично методу self::query(), только метод принимает только два параметра -
     * SQL запрос $query и массив аргументов $arguments, которые и будут заменены на заменители в той
     * последовательности, в которой они представленны в массиве $arguments.
     *
     * @param string
     * @param array
     * @return bool|Statement
     */
    public function queryArguments($query, array $arguments=array())
    {
        array_unshift($arguments, $query);

        return call_user_func_array(array($this, 'query'), $arguments);
    }

    /**
     * Обёртка над методом $this->parse().
     * Применяется для случаев, когда SQL-запрос формируется частями.
     *
     * Пример:
     *     $db->prepare('WHERE `name` = "?s" OR `id` IN(?ai)', 'Василий', array(1, 2));
     * Результат:
     *     WHERE `name` = "Василий" OR `id` IN(1, 2)
     *
     * @param string SQL-запрос или его часть
     * @param mixed аргументы заполнителей
     * @return boolean|string
     */
    public function prepare()
    {
        if (!func_num_args()) {
            return false;
        }

        $args = func_get_args();
        $query = array_shift($args);

        return $this->parse($query, $args);
    }

    /**
     * Получает количество рядов, задействованных в предыдущей MySQL-операции.
     * Возвращает количество рядов, задействованных в последнем запросе INSERT, UPDATE или DELETE.
     * Если последним запросом был DELETE без оператора WHERE,
     * все записи таблицы будут удалены, но функция возвратит ноль.
     *
     * @see mysqli_affected_rows
     * @return int
     */
    public function getAffectedRows()
    {
        return $this->mysqli->affected_rows;
    }

    /**
     * Возвращает последний оригинальный SQL-запрос до преобразования.
     *
     * @return string
     */
    public function getOriginalQueryString()
    {
        return $this->original_query;
    }

    /**
     * Возвращает последний выполненный MySQL-запрос (после преобразования).
     *
     * @return string
     */
    public function getQueryString()
    {
        return $this->query;
    }

    /**
     * Возвращает массив со всеми исполненными SQL-запросами в рамках текущего объекта.
     *
     * @return array
     */
    public function getQueries()
    {
        return $this->queries;
    }

    /**
     * Возвращает id, сгенерированный предыдущей операцией INSERT.
     *
     * @return int
     */
    public function getLastInsertId()
    {
        return $this->mysqli->insert_id;
    }

    /**
     * Возвращает оригинальный объект mysqli.
     *
     * @return \mysqli
     */
    public function getMysqli()
    {
        return $this->mysqli;
    }

    public function __destruct()
    {
        $this->close();
    }

    public function __sleep()
    {
        return [
            'server', 'user', 'password', 'port', 'socket',
            'database_name',
            'type_mode', 'store_queries', 'query', 'original_query'
        ];
    }

    public function __wakeup()
    {
        $this
            ->connect()
            ->setDatabaseName($this->database_name);
    }

    /**
     * @param string $server
     * @param string $username
     * @param string $password
     * @param string $port
     * @param string $socket
     * @return void
     */
    private function __construct($server, $user, $password, $port, $socket)
    {
        $this->server   = $server;
        $this->user = $user;
        $this->password = $password;
        $this->port = $port;
        $this->socket = $socket;

        $this->connect();
    }

    /**
     * Устанавливает соеденение с базой данных.
     *
     * @return $this
     * @throws MySqlException
     */
    private function connect()
    {
        if (!is_object($this->mysqli) || !$this->mysqli instanceof \mysqli) {
            $this->mysqli = @new \mysqli(
                $this->server,
                $this->user,
                $this->password,
                null,
                $this->port,
                $this->socket
            );

            if ($this->mysqli->connect_error) {
                throw new MySqlException(__METHOD__ . ': ' . $this->mysqli->connect_error);
            }
        }

        return $this;
    }

    /**
     * Закрывает MySQL-соединение.
     *
     * @return Mysql
     */
    private function close()
    {
        if (is_object($this->mysqli) && $this->mysqli instanceof \mysqli) {
            $this->mysqli->close();
        }

        return $this;
    }

    /**
     * Возвращает экранированную строку для placeholder-а поиска LIKE (?S).
     *
     * @param string $var строка в которой необходимо экранировать спец. символы
     * @param string $chars набор символов, которые так же необходимо экранировать.
     *                      По умолчанию экранируются следующие символы: `'"%_`.
     * @return string
     */
    private function escapeLike($var, $chars = "%_")
    {
        $var = str_replace('\\', '\\\\', $var);
        $var = $this->mysqlRealEscapeString($var);

        if ($chars) {
            $var = addCslashes($var, $chars);
        }

        return $var;
    }

    /**
     * Экранирует специальные символы в строке для использования в SQL выражении,
     * используя текущий набор символов соединения.
     *
     * @see mysqli_real_escape_string
     * @param string
     * @return string
     */
    private function mysqlRealEscapeString($value)
    {
        return $this->mysqli->real_escape_string($value);
    }

    /**
     * Возвращает строку описания ошибки при несовпадении типов заполнителей и аргументов.
     *
     * @param string $type тип заполнителя
     * @param mixed $value значение аргумента
     * @param string $original_query оригинальный SQL-запрос
     * @return string
     */
    private function createErrorMessage($type, $value, $original_query)
    {
        return "Попытка указать для заполнителя типа $type значение типа " .
                gettype($value) . " в шаблоне запроса $original_query";
    }

    /**
     * Парсит запрос $query и подставляет в него аргументы из $args.
     *
     * @param string $query SQL запрос или его часть (в случае парсинга условия в скобках [])
     * @param array $args аргументы заполнителей
     * @param string $original_query "оригинальный", полный SQL-запрос
     * @return string SQL запрос для исполнения
     */
    private function parse($query, array $args, $original_query=null)
    {
        $original_query = $original_query ? $original_query : $query;

        $offset = 0;

        while (($posQM = mb_strpos($query, '?', $offset)) !== false) {
            $offset = $posQM;

            $placeholder_type = mb_substr($query, $posQM + 1, 1);

            // Любые ситуации с нахождением знака вопроса, который не явялется заполнителем.
            if ($placeholder_type == '' || !in_array($placeholder_type, array('i', 'd', 's', 'S', 'n', 'A', 'a', 'f'))) {
                $offset += 1;
                continue;
            }

            if (!$args) {
                throw new MySqlException(
                    __METHOD__ . ': количество заполнителей в запросе ' . $original_query .
                    ' не соответствует переданному количеству аргументов'
                );
            }

            $value = array_shift($args);

            $is_associative_array = false;

            switch ($placeholder_type) {
                // `LIKE` search escaping
                case 'S':
                    $is_like_escaping = true;

                // Simple string escaping
                // В случае установки MODE_TRANSFORM режима, преобразование происходит согласно правилам php типизации
                // http://php.net/manual/ru/language.types.string.php#language.types.string.casting
                // для bool, null и numeric типа.
                case 's':
                    $value = $this->getValueStringType($value, $original_query);
                    $value = !empty($is_like_escaping) ? $this->escapeLike($value) : $this->mysqlRealEscapeString($value);
                    $query = mb_substr_replace($query, $value, $posQM, 2);
                    $offset += mb_strlen($value);
                    break;

                // Integer
                // В случае установки MODE_TRANSFORM режима, преобразование происходит согласно правилам php типизации
                // http://php.net/manual/ru/language.types.integer.php#language.types.integer.casting
                // для bool, null и string типа.
                case 'i':
                    $value = $this->getValueIntType($value, $original_query);
                    $query = mb_substr_replace($query, $value, $posQM, 2);
                    $offset += mb_strlen($value);
                    break;

                // double
                case 'd':
                    $value = $this->getValueFloatType($value, $original_query);
                    $query = mb_substr_replace($query, $value, $posQM, 2);
                    $offset += mb_strlen($value);
                    break;

                // NULL insert
                case 'n':
                    $value = $this->getValueNullType($value, $original_query);
                    $query = mb_substr_replace($query, $value, $posQM, 2);
                    $offset += mb_strlen($value);
                    break;

                // field or table name
                case 'f':
                    $value = $this->escapeFieldName($value, $original_query);
                    $query = mb_substr_replace($query, $value, $posQM, 2);
                    $offset += mb_strlen($value);
                    break;

                // Парсинг массивов.

                // Associative array
                case 'A':
                    $is_associative_array = true;

                // Simple array
                case 'a':
                    $value = $this->getValueArrayType($value, $original_query);

                    $next_char = mb_substr($query, $posQM + 2, 1);

                    if ($next_char != '' && preg_match('#[sid\[]#u', $next_char, $matches)) {
                        // Парсим выражение вида ?a[?i, "?s", "?s"]
                        if ($next_char == '[' and ($close = mb_strpos($query, ']', $posQM+3)) !== false) {
                            // Выражение между скобками [ и ]
                            $array_parse = mb_substr($query, $posQM+3, $close - ($posQM+3));
                            $array_parse = trim($array_parse);
                            $placeholders = array_map('trim', explode(',', $array_parse));

                            if (count($value) != count($placeholders)) {
                                throw new MySqlException(
                                    'Несовпадение количества аргументов и заполнителей в массиве, запрос ' .
                                    $original_query
                                );
                            }

                            reset($value);
                            reset($placeholders);

                            $replacements = array();

                            $i = 0;
                            foreach ($value as $key => $val) {
                                $replacements[$key] = $this->parse($placeholders[$i], array($val), $original_query);
                                $i++;
                            }

                            if (!empty($is_associative_array)) {
                                foreach ($replacements as $key => $val) {
                                    $values[] = $this->escapeFieldName($key, $original_query) . ' = ' . $val;
                                }

                                $value = implode(',', $values);
                            } else {
                                $value = implode(', ', $replacements);
                            }

                            $query = mb_substr_replace(
                                $query,
                                $value,
                                $posQM,
                                4 + mb_strlen($array_parse)
                            );
                            $offset += mb_strlen($value);
                        }
                        // Выражение вида ?ai, ?as, ?ap
                        else if (preg_match('#[sid]#u', $next_char, $matches)) {
                            $sql = '';
                            $parts = array();

                            foreach ($value as $key => $val) {
                                switch ($matches[0]) {
                                    case 's':
                                        $val = $this->getValueStringType($val, $original_query);
                                        $val = $this->mysqlRealEscapeString($val);
                                        break;
                                    case 'i':
                                        $val = $this->getValueIntType($val, $original_query);
                                        break;
                                    case 'd':
                                        $val = $this->getValueFloatType($val, $original_query);
                                        break;
                                }

                                if (!empty($is_associative_array)) {
                                    $parts[] = $this->escapeFieldName($key, $original_query) . ' = "' . $val . '"';
                                } else {
                                    $parts[] = '"' . $val . '"';
                                }
                            }

                            $value = implode(', ', $parts);
                            $value = $value !== '' ? $value : 'NULL';

                            $query = mb_substr_replace($query, $value, $posQM, 3);
                            $offset += mb_strlen($value);
                        }
                    } else {
                        throw new MySqlException(
                            'Попытка воспользоваться заполнителем массива без указания типа данных его элементов'
                        );
                    }

                    break;
            }
        }

        return $query;
    }

    /**
     * В зависимости от типа режима возвращает либо строковое значение $value,
     * либо кидает исключение.
     *
     * @param mixed $value
     * @param string $original_query оригинальный SQL запрос
     * @throws Exception
     * @return string
     */
    private function getValueStringType($value, $original_query)
    {
        if (!is_string($value) && $this->type_mode == self::MODE_STRICT) {
            // Если это числовой string, меняем его тип для вывода в тексте исключения его типа.
            if ($this->isInteger($value) || $this->isFloat($value)) {
                $value += 0;
            }

            throw new MySqlException($this->createErrorMessage('string', $value, $original_query));
        }

        // меняем поведение PHP в отношении приведения bool к string
        if (is_bool($value)) {
            return (string) (int) $value;
        }

        if (!is_string($value) && !(is_numeric($value) || is_null($value))) {
            throw new MySqlException($this->createErrorMessage('string', $value, $original_query));
        }

        return (string) $value;
    }

    /**
     * В зависимости от типа режима возвращает либо строковое значение числа $value,
     * приведенного к типу int, либо кидает исключение.
     *
     * @param mixed $value
     * @param string $original_query оригинальный SQL запрос
     * @throws Exception
     * @return string
     */
    private function getValueIntType($value, $original_query)
    {
        if ($this->isInteger($value)) {
            return $value;
        }

        switch ($this->type_mode) {
            case self::MODE_TRANSFORM:
                if ($this->isFloat($value) || is_null($value) || is_bool($value)) {
                    return (int) $value;
                }

            case self::MODE_STRICT:
                // Если это числовой string, меняем его тип для вывода в тексте исключения его типа.
                if ($this->isFloat($value)) {
                    $value += 0;
                }
                throw new MySqlException($this->createErrorMessage('integer', $value, $original_query));
        }
    }

    /**
     * В зависимости от типа режима возвращает либо строковое значение числа $value,
     * приведенного к типу float, либо кидает исключение.
     *
     * Внимание! Разделитель целой и дробной части, возвращаемый float, может не совпадать с разделителем СУБД.
     * Для установки необходимого разделителя дробной части используйте setlocale().
     *
     * @param mixed $value
     * @param string $original_query оригинальный SQL запрос
     * @throws Exception
     * @return string
     */
    private function getValueFloatType($value, $original_query)
    {
        if ($this->isFloat($value)) {
            return $value;
        }

        switch ($this->type_mode) {
            case self::MODE_TRANSFORM:
                if ($this->isInteger($value) || is_null($value) || is_bool($value)) {
                    return (float) $value;
                }

            case self::MODE_STRICT:
                // Если это числовой string, меняем его тип на int для вывода в тексте исключения.
                if ($this->isInteger($value)) {
                    $value += 0;
                }
                throw new MySqlException($this->createErrorMessage('double', $value, $original_query));
        }
    }

    /**
     * В зависимости от типа режима возвращает либо строковое значение 'NULL',
     * либо кидает исключение.
     *
     * @param mixed $value
     * @param string $original_query оригинальный SQL запрос
     * @throws Exception
     * @return string
     */
    private function getValueNullType($value, $original_query)
    {
        if ($value !== null && $this->type_mode == self::MODE_STRICT) {
            // Если это числовой string, меняем его тип для вывода в тексте исключения его типа.
            if ($this->isInteger($value) || $this->isFloat($value)) {
                $value += 0;
            }

            throw new MySqlException($this->createErrorMessage('NULL', $value, $original_query));
        }

        return 'NULL';
    }

    /**
     * Всегда генерирует исключение, если $value не является массивом.
     * Первоначально была идея в режиме self::MODE_TRANSFORM приводить к типу array
     * скалярные данные, но на данный момент я считаю это излишним послаблением для клиентов,
     * которые будут использовать данный класс.
     *
     * @param mixed $value
     * @param string $original_query
     * @throws Exception
     * @return array
     */
    private function getValueArrayType($value, $original_query)
    {
        if (!is_array($value)) {
            throw new MySqlException($this->createErrorMessage('array', $value, $original_query));
        }

        return $value;
    }

    /**
     * Экранирует имя поля таблицы или столбца.
     *
     * @param string $value
     * @return string $value
     */
    private function escapeFieldName($value, $original_query)
    {
        if (!is_string($value)) {
            throw new MySqlException($this->createErrorMessage('field', $value, $original_query));
        }

        $new_value = '';

        $replace = function($value){
            return '`' . str_replace("`", "``", $value) . '`';
        };

        // Признак обнаружения символа текущей базы данных
        $dot = false;

        if ($values = explode('.', $value)) {
            foreach ($values as $value) {
                if ($value === '') {
                    if (!$dot) {
                        $dot = true;
                        $new_value .= '.';
                    } else {
                        throw new MySqlException('Два символа `.` идущие подряд в имени столбца или таблицы');
                    }
                } else {
                    $new_value .= $replace($value) . '.';
                }
            }

            return rtrim($new_value, '.');
        } else {
            return $replace($value);
        }
    }

    /**
     * Проверяет, является ли значение целым числом, умещающимся в диапазон PHP_INT_MAX.
     *
     * @param mixed $input
     * @return boolean
     */
    private function isInteger($val)
    {
        if (!is_scalar($val) || is_bool($val)) {
            return false;
        }

        return $this->isFloat($val) ? false : preg_match('~^((?:\+|-)?[0-9]+)$~', $val) === 1;
    }

    /**
     * Проверяет, является ли значение числом с плавающей точкой.
     *
     * @param mixed $input
     * @return boolean
     */
    private function isFloat($val)
    {
        if (!is_scalar($val) || is_bool($val)) {
            return false;
        }

        $type = gettype($val);

        if ($type === "double") {
            return true;
        } else {
            return preg_match("/^([+-]*\\d+)*\\.(\\d+)*$/", $val) === 1;
        }
    }
}

/**
 * Заменяет часть строки string, начинающуюся с символа с порядковым номером start
 * и (необязательной) длиной length, строкой replacement и возвращает результат.
 *
 * @param string $string
 * @param string $replacement
 * @param string $start
 * @param string $length
 * @param string $encoding
 * @return string
 */
if (!function_exists("mb_substr_replace"))
{
    function mb_substr_replace($string, $replacement, $start, $length = null, $encoding = null)
    {
        if ($encoding == null) {
            $encoding = mb_internal_encoding();
        }

        if ($length == null) {
            return mb_substr($string, 0, $start, $encoding) . $replacement;
        } else {
            if ($length < 0) {
                $length = mb_strlen($string, $encoding) - $start + $length;
            }

            return
                mb_substr($string, 0, $start, $encoding) .
                $replacement .
                mb_substr($string, $start + $length, mb_strlen($string, $encoding), $encoding);
        }
    }
}