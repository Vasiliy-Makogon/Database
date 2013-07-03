<?php
/**
 * @author Vasiliy Makogon, makogon.vs@gmail.com, makogon-vs@yandex.ru
 * @link http://www.phpinfo.su/
 *
 * Данный класс использует технологию placeholders - для формирования корректных SQL-запросов, в строке запроса вместо
 * значений пишутся специальные типизированные маркеры - заполнители, а сами данные передаются "позже", в качестве
 * последующих аргументов основного метода, выполняющего SQL-запрос - Krugozor_Database_Mysql::query($sql [, $arg, $...]):
 *
 *     $db->query('SELECT * FROM `table` WHERE `field_1` = "?s" AND `field_2` = ?i', 'вася', 30);
 *
 * Аргументы SQL-запроса, прошедшие через систему placeholders, экранируются специальными функциями экранирования,
 * в зависимости от типа заполнителей. Т.е. вам нет необходимости заключать переменные в функции
 * экранирования типа mysqli_real_escape_string($value) или приводить их к числовому типу через (int)$value.
 *
 *
 *    Типы заполнителей
 *
 * ?f - заполнитель имени таблицы или поля.
 *
 * ?i - заполнитель числового типа.
 *      В режиме MODE_TRANSFORM любые скалярные данные принудительно приводятся к типу integer
 *      согласно правилам преобразования к типу integer в PHP.
 *
 * ?s - заполнитель строкового типа.
 *      В режиме MODE_TRANSFORM любые скалярные данные принудительно приводятся к типу string
 *      согласно правилам преобразования к типу string в PHP
 *      и экранируются с помощью функции PHP mysqli_real_escape_string().
 *
 * ?S - заполнитель строкового типа для подстановки в SQL-оператор LIKE.
 *      В режиме MODE_TRANSFORM Любые скалярные данные принудительно приводятся к типу string
 *      согласно правилам преобразования к типу string в PHP
 *      и экранируются с помощью функции PHP mysqli_real_escape_string() + экранирование спецсимволов,
 *      используемых в операторе LIKE (%_).
 *
 * ?n - заполнитель NULL типа.
 *      В режиме MODE_TRANSFORM любые данные игнорируются, заполнители заменяются на строку `NULL` в SQL запросе.
 *
 * ?A* - заполнитель ассоциативного множества из ассоциативного массива, генерирующий последовательность
 *       пар ключ => значение.
 *       Пример: "key_1" = "val_1", "key_2" = "val_2", ...
 *
 * ?a* - заполнитель множества из простого (или также ассоциативного) массива, генерирующий последовательность
 *       значений.
 *       Пример: "val_1", "val_2", ...
 *
 *       где * после заполнителя - один из типов:
 *       - i (int)
 *       - s (string)
 *       правила преобразования и экранирования такие же, как и для одиночных скалярных типов (см. выше).
 *
 * ?A[?n, ?s, ?i] - заполнитель ассоциативного множества с явным указанием типа и количества аргументов,
 *                  генерирующий последовательность пар ключ => значение.
 *                  Пример: "key_1" = "val_1", "key_2" => "val_2", ...
 *
 * ?a[?n, ?s, ?i] - заполнитель множества с явным указанием типа и количества аргументов, генерирующий последовательность
 *                  значений.
 *                  Пример: "val_1", "val_2", ...
 *
 *
 *    Режимы работы.
 *
 * Существует два режима работы метода:
 * Krugozor_Database_Mysql::MODE_STRICT    - строгий режим соответствия типа заполнителя и типа аргумента.
 * Krugozor_Database_Mysql::MODE_TRANSFORM - режим преобразования аргумента к типу заполнителя при несовпадении
 *                                           типа заполнителя и типа аргумента. Установлен по умолчанию.
 *
 *
 *     MODE_STRICT
 *
 * В "строгом" режиме MODE_STRICT аргументы, передаваемые в основной метод
 * Krugozor_Database_Mysql::query(), должны в ТОЧНОСТИ соответствовать типу заполнителя.
 * Разберем примеры:
 *
 * $db->query('SELECT * FROM `table` WHERE `field` = ?i', 'вася'); - в данном случае будет выброшено исключение
 *     "Попытка записать как int значение вася типа string в запросе ...", т.к.
 * указан тип заполнителя ?i (int - целое число), а в качестве аргумента передается строка 'вася'.
 *
 * $db->query('SELECT * FROM `table` WHERE `field` = "?s"', 123); - будет выброшено исключение
 *     "Попытка записать как string значение 123 типа integer в запросе ...", т.к.
 * указан тип заполнителя ?s (string - строка), а в качестве аргумента передается число 123.
 *
 * $db->query('SELECT * FROM `table` WHERE `field` IN (?as)', array(null, 123, true, 'string')); - будет выброшено исключение
 *     "Попытка записать как string значение типа NULL в запросе ...", т.к. заполнитель множества ?as ожидает,
 * что все элементы массива-аргумета будут типа s (string - строка), но на деле все элементы массива представляют собой
 * данные различных типов. Парсер прекратил разбор на первом несоответствии типа заполнителя и типа аргумента - на
 * элементе массива со значением null.

 *
 *     MODE_TRANSFORM
 *
 * Режим MODE_TRANSFORM является "щадящим" режимом и при несоответствии типа заполнителя и аргумента не генерирует
 * исключение, а пытается преобразовать аргумент к нужному типу в соответствии с правилами преобразования типов в PHP.
 *
 * Допускаются следующие преобразования:
 *
 * К строковому типу приводятся данные типа boolean, numeric, NULL:
 *     - значение boolean TRUE преобразуется в строку "1", а значение FALSE преобразуется в "" (пустую строку).
 *     - значение типа numeric преобразуется в строку согласно правилам преобразования, определенным языком.
 *     - NULL преобразуется в пустую строку.
 * Для массивов, объектов и ресурсов преобразования не допускаются.
 *
 * Пример выражения:
 *     $db->query('SELECT * FROM `table` WHERE f1 = "?s", f2 = "?s", f3 = "?s"', null, 123, true);
 * Результат преобразования:
 *     SELECT * FROM `table` WHERE f1 = "", f2 = "123", f3 = "1"
 *
 * К целочисленному типу приводятся данные типа boolean, string, NULL:
 *     - значение boolean FALSE преобразуется в 0 (ноль), а TRUE - в 1 (единицу).
 *     - значение типа string преобразуется согласно правилам преобразования, определенным языком.
 *     - NULL преобразуется в 0.
 * Для массивов, объектов и ресурсов преобразования не допускаются.
 *
 * Пример выражения:
 *     $db->query('SELECT * FROM `table` WHERE f1 = ?i, f2 = ?i, f3 = ?i, f4 = ?i', null, '123abc', 'abc', true);
 * Результат преобразования:
 *     SELECT * FROM `table` WHERE f1 = 0, f2 = 123, f3 = 0, f4 = 1
 *
 * NULL тип замещает аргумент для любого типа данных.
 *
 *
 *    Ограничивающие кавчки
 *
 * Данный класс при формировании SQL-запроса НЕ занимается проставлением ограничивающих кавычек для одиночных
 * заполнителей скалярного типа, таких как ?i и ?s. Это сделано по идеологическим соображениям, автоподстановка кавычек
 * может стать ограничением для возможностей SQL.
 * Например, выражение
 *     $db->query('SELECT "Total: ?s"', '200');
 * вернёт строку
 *     'Total: 200'
 * Если бы кавычки, ограничивающие строковой литерал, ставились бы автоматически,
 * то вышеприведённое условие вернуло бы строку
 *     'Total: "200"'
 * что было бы не ожидаемым поведением.
 *
 * Тем не менее, для перечислений ?as, ?ai, ?As и ?Ai ограничивающие кавычки ставятся принудительно, т.к.
 * перечисления всегда используются в запросах, где наличие кавчек обязательно или не играет роли:
 *
 *    $db->query('INSERT INTO test SET ?As', array('name' => 'Маша', 'age' => '23', 'adress' => 'Москва'));
 *    -> INSERT INTO test SET `name` = "Маша", `age` = "23", `adress` = "Москва"
 *
 *    $db->query('SELECT * FROM table WHERE field IN (?as)', array('55', '12', '132'));
 *    -> SELECT * FROM table WHERE field IN ("55", "12", "132")
 */
class Krugozor_Database_Mysql
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
     *   Строка '2+мусор' будет принудительно приведена к типу int - к числу 2.
     *
     * @var int
     */
    const MODE_TRANSFORM = 2;

    /**
     * Режим работы. См. описание констант self::MODE_STRICT и self::MODE_TRANSFORM.
     *
     * @var int
     */
    protected $type_mode = self::MODE_TRANSFORM;

    protected $server;

    protected $user;

    protected $password;

    protected $port;

    protected $socket;

    /**
     * Имя текущей БД.
     *
     * @var string
     */
    protected $database_name;

    /**
     * Объект соединения с БД.
     *
     * @var mysqli
     */
    protected $lnk;

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
     * Ключи - SQL до преобразования, значения - после.
     *
     * @var array
     */
    private static $queries = array();

    /**
     * Массив имен полей запрошенной таблицы.
     * Это свойство для дополнительного функционала, см. метод $this->getListFields().
     * Вы можете в случае отсутствия необходимости удалить это свойство и метод $this->getListFields()
     * т.к. в данном классе они присутствуют исключительно по историческим причинам и вопросам совместимости с моим
     * старым кодом. Хотя, безусловно, это полезный метод и вполне возможно, рано или поздно он вам пригодится.
     *
     * @var array
     */
    private static $list_fields = array();

    /**
     * Создает инстанс данного класса.
     *
     * @param string $server имя сервера
     * @param string $username имя пользователя
     * @param string $password пароль
     * @param string $port порт
     * @param string $socket сокет
     */
    public static function create($server, $username, $password, $port=null, $socket=null)
    {
        return new self($server, $username, $password, $port, $socket);
    }

    /**
     * @see mysqli_set_charset
     * @param string $charset
     * @return Krugozor_Database_Mysql
     */
    public function setCharset($charset)
    {
        if (!mysqli_set_charset($this->lnk, $charset))
        {
            throw new Exception(__METHOD__ . ': ' . mysqli_error($this->lnk));
        }

        return $this;
    }

    /**
     * @see mysqli_character_set_name
     * @param void
     * @return string
     */
    public function getCharset()
    {
        return mysqli_character_set_name($this->lnk);
    }

    /**
     * Устанавливает имя используемой СУБД.
     *
     * @param string имя базы данных
     * @return Krugozor_Database_Mysql
     */
    public function setDatabaseName($database_name)
    {
        if (!$database_name)
        {
            throw new Exception(__METHOD__ . ': Не указано имя базы данных');
        }

        $this->database_name = $database_name;

        if (!mysqli_select_db($this->lnk, $this->database_name))
        {
            throw new Exception(__METHOD__ . ': ' . mysqli_error($this->lnk));
        }

        return $this;
    }

    /**
     * Возвращает имя текущей БД.
     *
     * @param void
     * @return string
     */
    public function getDatabaseName()
    {
        return $this->database_name;
    }

    /**
     * Устанавливает режим поведения при несовпадении типа заполнителя и аргумента.
     *
     * @param $value int
     * @return Krugozor_Database_Mysql
     */
    public function setTypeMode($value)
    {
    	if (!in_array($value, array(self::MODE_STRICT, self::MODE_TRANSFORM)))
    	{
    		throw new Exception('Указан неизвестный тип режима');
    	}

        $this->type_mode = $value;

        return $this;
    }

    /**
     * Выполняет SQL-запрос.
     * Принимает обязательный параметр - SQL-запрос и, в случае наличия,
     * любое количество аргументов - значения заполнителей.
     *
     * @param string строка SQL-запроса
     * @param mixed аргументы для заполнителей
     * @return bool|Krugozor_Database_Mysql_Statement
     */
    public function query()
    {
        if (!func_num_args())
        {
            return false;
        }

        $args = func_get_args();

        $query = $this->original_query = array_shift($args);

        $this->query = $this->parse($query, $args);

        $result = mysqli_query($this->lnk, $this->query);

        self::$queries[$this->original_query] = $this->query;

        if ($result === false)
        {
            throw new Exception(__METHOD__ . ': ' . mysqli_error($this->lnk) . '; SQL: ' . $this->query);
        }

        if (is_object($result) && $result instanceof mysqli_result)
        {
            return new Krugozor_Database_Mysql_Statement($result);
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
     * @return bool|Krugozor_Database_Mysql_Statement
     */
    public function queryArguments($query, array $arguments=array())
    {
        array_unshift($arguments, $query);

        return call_user_func_array(array($this, 'query'), $arguments);
    }

    /**
     * Получает количество рядов,
     * задействованных в предыдущей MySQL-операции.
     * Возвращает количество рядов, задействованных в последнем запросе INSERT, UPDATE или DELETE.
     * Если последним запросом был DELETE без оператора WHERE,
     * все записи таблицы будут удалены, но функция возвратит ноль.
     *
     * @see mysqli_affected_rows
     * @param void
     * @return int
     */
    public function getAffectedRows()
    {
        return mysqli_affected_rows($this->lnk);
    }

    /**
     * Возвращает последний оригинальный SQL-запрос до преобразования.
     *
     * @param void
     * @return string
     */
    public function getOriginalQueryString()
    {
        return $this->original_query;
    }

    /**
     * Возвращает последний выполненный MySQL-запрос.
     *
     * @param void
     * @return string
     */
    public function getQueryString()
    {
        return $this->query;
    }

    /**
     * Возвращает массив со всеми исполненными SQL-запросами в рамках текущего объекта.
     *
     * @param void
     * @return array
     */
    public function getQueries()
    {
        return self::$queries;
    }

    /**
     * Возвращает id, сгенерированный предыдущей операцией INSERT.
     *
     * @param void
     * @return int
     */
    public function getLastInsertId()
    {
        return mysqli_insert_id($this->lnk);
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * Возвращает массив объектов stdClass, содержащих свойства таблицы $table.
     *
     * @param string имя таблицы
     * @return array
     */
    public function getListFields($table)
    {
        if (!isset(self::$list_fields[$table]))
        {
            $result = $this->query('SELECT * FROM `' . $this->database_name . '`.`' . $table . '` LIMIT 1');

            $finfo = mysqli_fetch_fields($result->getResult());

            foreach ($finfo as $obj)
            {
                self::$list_fields[$table][$obj->name] = $obj;
            }
        }

        return self::$list_fields[$table];
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
     * @param void
     * @return void
     */
    private function connect()
    {
        if (!is_object($this->lnk) || !$this->lnk instanceof mysqli)
        {
            if (!$this->lnk = @mysqli_connect($this->server, $this->user, $this->password, null, $this->port, $this->socket))
            {
                throw new Exception(__METHOD__ . ': ' . mysqli_connect_error());
            }
        }
    }

    /**
     * Закрывает MySQL-соединение.
     *
     * @param void
     * @return Krugozor_Database_Mysql
     */
    private function close()
    {
        if (is_object($this->lnk) && $this->lnk instanceof mysqli)
        {
            @mysqli_close($this->lnk);
        }

        return $this;
    }

    /**
     * Возвращает экранированную строку для placeholder-а поиска LIKE.
     *
     * @param string $var строка в которой необходимо экранировать спец. символы
     * @param string $chars набор символов, которые так же необходимо экранировать.
     *                      По умолчанию экранируются следующие символы: `'"%_`.
     * @return string
     */
    private function escape_like($var, $chars = "%_")
    {
        $var = str_replace('\\', '\\\\', $var);
        $var = $this->mysqlRealEscapeString($var);

        if ($chars)
        {
            $var = addCslashes($var, $chars);
        }

        return $var;
    }

    /**
    * @see mysqli_real_escape_string
    * @param string
    * @return string
    */
    private function mysqlRealEscapeString($value)
    {
        return mysqli_real_escape_string($this->lnk, $value);
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
        return __CLASS__ . ': Попытка записать как ' . $type . ' значение ' . print_r($value, true) . ' типа ' .
               gettype($value) . ' в запросе ' . $original_query;
    }

    /**
     * Парсит запрос $query и подставляет в него аргументы из $args.
     *
     * @param string $query
     * @param array $args
     * @param string $original_query
     * @return string
     */
    private function parse($query, array $args, $original_query=null)
    {
        $original_query = $original_query ? $original_query : $query;

        $offset = 0;

        while (($posQM = strpos($query, '?', $offset)) !== false)
        {
            $offset = $posQM;

            if (!isset($query[$posQM + 1]))
            {
                continue;
            }
            else
            {
            	// Если найден просто знак ?, парсим строку дальше.
            	if (!in_array($query[$posQM + 1], array('i', 's', 'S', 'n', 'A', 'a', 'f')))
            	{
            		$offset += 1;
            		continue;
            	}
            }

            if (!$args)
            {
                throw new Exception(__METHOD__ . ': количество заполнителей в запросе ' . $original_query .
                                    ' не соответствует переданному количеству аргументов');
            }

            $value = array_shift($args);

            switch ($query[$posQM + 1])
            {
                // `LIKE` search escaping
                case 'S':
                    $is_like_escaping = true;

                // Simple string escaping
                // В случае установки MODE_TRANSFORM режима, преобразование происходит согласно правилам php типизации
                // http://php.net/manual/ru/language.types.string.php#language.types.string.casting
                // для bool, null и numeric типа.
                case 's':
                    $value = $this->getValueStringType($value, $original_query);
                    $value = !empty($is_like_escaping) ? $this->escape_like($value) : $this->mysqlRealEscapeString($value);
                    $query = substr_replace($query, $value, $posQM, 2);
                    $offset += strlen($value);
                    break;

                // Integer
                // В случае установки MODE_TRANSFORM режима, преобразование происходит согласно правилам php типизации
                // http://php.net/manual/ru/language.types.integer.php#language.types.integer.casting
                // для bool, null и string типа.
                case 'i':
                    $value = $this->getValueIntType($value, $original_query);
                    $query = substr_replace($query, $value, $posQM, 2);
                    $offset += strlen($value);
                    break;

                // NULL insert
                case 'n':
                    $value = $this->getValueNullType($value, $original_query);
                    $query = substr_replace($query, $value, $posQM, 2);
                    $offset += strlen($value);
                    break;

                // field or table name
                case 'f':
                    $value = '`' . $this->escapeFieldName($value) . '`';
                    $query = substr_replace($query, $value, $posQM, 2);
                    $offset += strlen($value);
                    break;

                // Парсинг массивов.

                // Associative array
                case 'A':
                    $is_associative_array = true;

                // Simple array
                case 'a':
                    $value = $this->getValueArrayType($value, $original_query);

                    if (isset($query[$posQM+2]) && preg_match('#[si\[]#', $query[$posQM+2], $matches))
                    {
                        // Парсим выражение вида ?a[?i, "?s", "?s"]
                        if ($query[$posQM+2] == '[' and ($close = strpos($query, ']', $posQM+3)) !== false)
                        {
                            // Выражение между скобками [ и ]
                            $array_parse = substr($query, $posQM+3, $close - ($posQM+3));
                            $array_parse = trim($array_parse);
                            $placeholders = array_map('trim', explode(',', $array_parse));

                            if (count($value) != count($placeholders))
                            {
                                throw new Exception('Несовпадение количества аргументов и заполнителей в массиве, запрос ' . $original_query);
                            }

                            reset($value);
                            reset($placeholders);

                            $replacements = array();

                            foreach ($placeholders as $placeholder)
                            {
                                list($key, $val) = each($value);
                                $replacements[$key] = $this->parse($placeholder, array($val), $original_query);
                            }

                            if (!empty($is_associative_array))
                            {
                                foreach ($replacements as $key => $val)
                                {
                                    $values[] = ' `' . $this->escapeFieldName($key) . '` = ' . $val;
                                }

                                $value = implode(',', $values);
                            }
                            else
                            {
                                $value = implode(', ', $replacements);
                            }

                            $query = substr_replace($query, $value, $posQM, 4 + strlen($array_parse));
                            $offset += strlen($value);
                        }
                        // Выражение вида ?ai или ?as
                        else if (preg_match('#[si]#', $query[$posQM+2], $matches))
                        {
                            $sql = '';
                            $parts = array();

                            foreach ($value as $key => $val)
                            {
                                switch ($matches[0])
                                {
                                    case 's':
                                        $val = $this->getValueStringType($val, $original_query);
                                        $val = $this->mysqlRealEscapeString($val);
                                        break;
                                    case 'i':
                                        $val = $this->getValueIntType($val, $original_query);
                                        break;
                                }

                                if (!empty($is_associative_array))
                                {
                                    $parts[] = ' `' . $this->escapeFieldName($key) . '` = "' . $val . '"';
                                }
                                else
                                {
                                    $parts[] = '"' . $val . '"';
                                }
                            }

                            $value = implode(', ', $parts);
                            $query = substr_replace($query, $value, $posQM, 3);
                            $offset += strlen($value);
                        }
                    }
                    else
                    {
                        throw new Exception('Попытка воспользоваться заполнителем массива без указания типа данных его элементов');
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
        if (!is_string($value))
        {
            if ($this->type_mode == self::MODE_STRICT)
            {
                throw new Exception($this->createErrorMessage('string', $value, $original_query));
            }
            else if ($this->type_mode == self::MODE_TRANSFORM)
            {
                if (is_numeric($value) || is_null($value) || is_bool($value))
                {
                    $value = (string)$value;
                }
                else
                {
                    throw new Exception($this->createErrorMessage('string', $value, $original_query));
                }
            }
        }

        return $value;
    }

    /**
     * В зависимости от типа режима возвращает либо строковое значение числа $value,
     * либо кидает исключение.
     *
     * @param mixed $value
     * @param string $original_query оригинальный SQL запрос
     * @throws Exception
     * @return string
     */
    private function getValueIntType($value, $original_query)
    {
        if (!is_numeric($value))
        {
            if ($this->type_mode == self::MODE_STRICT)
            {
                throw new Exception($this->createErrorMessage('int', $value, $original_query));
            }
            else if ($this->type_mode == self::MODE_TRANSFORM)
            {
                if (is_string($value) || is_null($value) || is_bool($value))
                {
                    $value = (int)$value;
                }
                else
                {
                    throw new Exception($this->createErrorMessage('int', $value, $original_query));
                }
            }
        }

        return (string)$value;
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
        if ($value !== null)
        {
            if ($this->type_mode == self::MODE_STRICT)
            {
                throw new Exception($this->createErrorMessage('NULL', $value, $original_query));
            }
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
        if (!is_array($value))
        {
            throw new Exception($this->createErrorMessage('array', $value, $original_query));
        }

        return $value;
    }

    /**
     * Экранирует имя поля таблицы в случае использования маркеров множества.
     *
     * @param string $value
     * @return string $value
     */
    private function escapeFieldName($value)
    {
        return str_replace("`", "``", $value);
    }
}