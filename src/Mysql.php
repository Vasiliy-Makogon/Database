<?php

namespace Krugozor\Database;

use mysqli;
use mysqli_result;

/**
 * @author Vasiliy Makogon
 * @link https://github.com/Vasiliy-Makogon/Database/
 */
class Mysql
{
    /** @var int Строгий режим работы */
    public const MODE_STRICT = 1;

    /** @var int Толерантный режим работы */
    public const MODE_TRANSFORM = 2;

    /** @var int Актуальный режим работы */
    protected int $type_mode = self::MODE_TRANSFORM;

    /** @var string|null */
    protected ?string $server;

    /** @var string|null */
    protected ?string $user;

    /** @var string|null */
    protected ?string $password;

    /** @var int|string|null */
    protected int|string|null $port;

    /** @var int|string|null */
    protected int|string|null $socket;

    /** @var string Имя текущей БД */
    protected string $database_name;

    /** @var mysqli|null */
    protected ?mysqli $mysqli = null;

    /** @var string|null Строка последнего SQL-запроса ДО преобразования */
    private ?string $original_query = null;

    /** @var string|null Строка последнего SQL-запроса ПОСЛЕ преобразования */
    private ?string $query = null;

    /**
     * Массив со всеми запросами, которые были выполнены объектом.
     * Ключи - SQL после преобразования, значения - SQL до преобразования.
     *
     * @var array
     */
    private array $queries = [];

    /** @var bool Накапливать ли в хранилище self::$queries SQL-запросы */
    private bool $store_queries = false;

    /** @var string Язык сообщений об ошибках */
    private string $lang = 'en';

    /**
     * Сообщения об ошибках на разных языках.
     *
     * @var array|array[]
     */
    protected array $i18n_error_messages = [
        'en' => [
            0 => '%s: error setting character encoding: %s',
            1 => '%s: database name not specified',
            2 => '%s: database selection error: %s',
            3 => '%s: unknown mode specified for library "%s", use allowed modes: "%s"',
            4 => '%s: no SQL query passed',
            5 => '%s: SQL query execution error: %s; SQL: %s',
            6 => 'attempted to set placeholder type "%s" to value type "%s" in query template "%s"',
            7 => '%s: number of placeholders in query "%s" does not match number of arguments passed',
            8 => 'Mismatch in the number of arguments and placeholders in the array, query: "%s"',
            9 => 'Attempting to use an array placeholder without specifying the data type of its elements',
            10 => 'Two consecutive `.` characters in a column or table name',
            11 => '%s: database connection error: %s',
        ],
        'ru' => [
            0 => '%s: ошибка установки кодировки: %s',
            1 => '%s: не указано имя базы данных',
            2 => '%s: ошибка выбора базы данных: %s',
            3 => '%s: указан неизвестный режим работы библиотеки "%s", используйте допустимые режимы: "%s"',
            4 => '%s: не передан SQL запрос',
            5 => '%s: ошибка выполнения SQL запроса: %s; SQL: "%s"',
            6 => 'попытка указать для заполнителя типа "%s" значение типа "%s" в шаблоне запроса "%s"',
            7 => '%s: количество заполнителей в запросе "%s" не соответствует переданному количеству аргументов',
            8 => 'Несовпадение количества аргументов и заполнителей в массиве, запрос: "%s", переданые аргументы: "%s"',
            9 => 'Попытка воспользоваться заполнителем массива без указания типа данных его элементов',
            10 => 'Два символа `.` идущие подряд в имени столбца или таблицы',
            11 => '%s: ошибка подключения к базе данных: %s',
        ],
    ];

    /**
     * @param string|null $server
     * @param string|null $username
     * @param string|null $password
     * @param int|string|null $port
     * @param int|string|null $socket
     * @return Mysql
     * @throws MySqlException
     */
    public static function create(
        string $server = null,
        string $username = null,
        string $password = null,
        int|string|null $port = null,
        int|string|null $socket = null
    ): Mysql {
        return new self($server, $username, $password, $port, $socket);
    }

    /**
     * Устанавливает язык вывода ошибок.
     *
     * @param string $lang
     * @return $this
     * @throws MySqlException
     */
    public function setErrorMessagesLang(string $lang): self
    {
        if (!array_key_exists($lang, $this->i18n_error_messages)) {
            throw new MySqlException(sprintf(
                '%s: language "%s" is not supported, use any of: "%s". ' .
                "Make a pull request for this library, or derive a new class from class 'Mysql' and add the " .
                "internationalization language for your language to property self::\$exception_i18n_messages",
                __METHOD__, $lang, implode('", "', array_keys($this->i18n_error_messages))
            ));
        }

        $this->lang = $lang;

        return $this;
    }

    /**
     * Задаёт набор символов, который будет использоваться при обмене данными с сервером баз данных.
     * Вызов данного метода эквивалентен следующей установки конфигурации MySql-сервера:
     * SET character_set_client = charset_name;
     * SET character_set_results = charset_name;
     * SET character_set_connection = charset_name;
     *
     * @param string $charset
     * @return Mysql
     * @throws MySqlException
     * @see mysqli::set_charset
     */
    public function setCharset(string $charset): Mysql
    {
        // Отлов в переменную $mysqli_sql_exception исключения при подключении
        // в режиме MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT
        $mysqli_sql_exception = null;

        try {
            // Подавление `собакой` вывода ошибки для режима MYSQLI_REPORT_ERROR
            $result = @$this->mysqli->set_charset($charset);
        } catch (\mysqli_sql_exception $mysqli_sql_exception) {
            $result = false;
        }

        // Выявляем источник данных об ошибке
        $error_code = $mysqli_sql_exception ? $mysqli_sql_exception->getCode() : $this->mysqli->errno;
        $error_message = $mysqli_sql_exception ? $mysqli_sql_exception->getMessage() : $this->mysqli->error;

        if ($result === false) {
            throw new MySqlException(sprintf(
                $this->i18n_error_messages[$this->lang][0],
                __METHOD__,
                $error_message
            ), $error_code, $mysqli_sql_exception);
        }

        return $this;
    }

    /**
     * Возвращает кодировку, установленную для соединения с БД.
     *
     * @param void
     * @return string
     * @see mysqli::character_set_name
     */
    public function getCharset(): string
    {
        return $this->mysqli->character_set_name();
    }

    /**
     * Устанавливает имя используемой СУБД.
     *
     * @param string имя базы данных
     * @return Mysql
     * @throws MySqlException
     * @see mysqli::select_db
     */
    public function setDatabaseName(string $database_name): Mysql
    {
        if (!$database_name) {
            throw new MySqlException(sprintf(
                $this->i18n_error_messages[$this->lang][1], __METHOD__
            ));
        }

        $this->database_name = $database_name;

        // Отлов в переменную $mysqli_sql_exception исключения при подключении
        // в режиме MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT
        $mysqli_sql_exception = null;

        try {
            // Подавление `собакой` вывода ошибки для режима MYSQLI_REPORT_ERROR
            $result = @$this->mysqli->select_db($this->database_name);
        } catch (\mysqli_sql_exception $mysqli_sql_exception) {
            $result = false;
        }

        // Выявляем источник данных об ошибке
        $error_code = $mysqli_sql_exception ? $mysqli_sql_exception->getCode() : $this->mysqli->errno;
        $error_message = $mysqli_sql_exception ? $mysqli_sql_exception->getMessage() : $this->mysqli->error;

        if ($result === false) {
            throw new MySqlException(sprintf(
                $this->i18n_error_messages[$this->lang][2],
                __METHOD__,
                $error_message
            ), $error_code, $mysqli_sql_exception);
        }

        return $this;
    }

    /**
     * Возвращает имя текущей БД.
     *
     * @return string
     */
    public function getDatabaseName(): string
    {
        return $this->database_name;
    }

    /**
     * Устанавливает режим поведения при несовпадении типа заполнителя и типа аргумента.
     *
     * @param int $type
     * @return Mysql
     * @throws MySqlException
     */
    public function setTypeMode(int $type): Mysql
    {
        if (!in_array($type, [self::MODE_STRICT, self::MODE_TRANSFORM])) {
            throw new MySqlException(sprintf(
                $this->i18n_error_messages[$this->lang][3],
                __METHOD__,
                $type,
                implode('", "', [self::MODE_STRICT, self::MODE_TRANSFORM])
            ));
        }

        $this->type_mode = $type;

        return $this;
    }

    /**
     * Устанавливает свойство $this->store_queries, отвечающее за накопление
     * исполненных запросов в хранилище $this->queries.
     *
     * @param bool $value
     * @return Mysql
     */
    public function setStoreQueries(bool $value): Mysql
    {
        $this->store_queries = $value;

        return $this;
    }

    /**
     * Выполняет SQL-запрос.
     * Принимает обязательный параметр - SQL-запрос и, в случае наличия,
     * любое количество аргументов - значения заполнителей.
     *
     * @param mixed ...$args строка SQL-запроса и аргументы для заполнителей
     * @return bool|Statement В случае успешного выполнения запросов, которые создают набор результатов,
     *         таких как SELECT, SHOW, DESCRIBE или EXPLAIN, метод вернёт объект Statement.
     *         Для остальных успешных запросов метод вернёт true.
     *         При ошибке последует исключение MySqlException
     * @throws MySqlException
     * @see mysqli::query
     */
    public function query(mixed ...$args): bool|Statement
    {
        if (!func_num_args()) {
            throw new MySqlException(sprintf(
                $this->i18n_error_messages[$this->lang][4], __METHOD__
            ));
        }

        $query = $this->original_query = array_shift($args);

        $this->query = $this->parse($query, $args);

        // Отлов в переменную $mysqli_sql_exception исключения при подключении
        // в режиме MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT
        $mysqli_sql_exception = null;

        try {
            // Подавление `собакой` вывода ошибки для режима MYSQLI_REPORT_ERROR
            $result = @$this->mysqli->query($this->query, MYSQLI_STORE_RESULT);
        } catch (\mysqli_sql_exception $mysqli_sql_exception) {
            $result = false;
        }

        if ($this->store_queries) {
            $this->queries[$this->query] = $this->original_query;
        }

        // Выявляем источник данных об ошибке
        $error_code = $mysqli_sql_exception ? $mysqli_sql_exception->getCode() : $this->mysqli->errno;
        $error_message = $mysqli_sql_exception ? $mysqli_sql_exception->getMessage() : $this->mysqli->error;

        if ($result === false) {
            throw new MySqlException(sprintf(
                $this->i18n_error_messages[$this->lang][5],
                __METHOD__,
                $error_message,
                $this->query
            ), $error_code, $mysqli_sql_exception);
        }

        if (is_object($result) && $result instanceof mysqli_result) {
            return new Statement($result);
        }

        return $result;
    }

    /**
     * Поведение аналогично методу self::query(), только метод принимает только два параметра -
     * SQL запрос $query и массив аргументов $arguments, которые и будут заменены на заменители в той
     * последовательности, в которой они представлены в массиве $arguments.
     *
     * @param string
     * @param array
     * @return bool|Statement В случае успешного выполнения запросов, которые создают набор результатов,
     *         таких как SELECT, SHOW, DESCRIBE или EXPLAIN, метод вернёт объект Statement.
     *         Для остальных успешных запросов метод вернёт true.
     *         При ошибке последует исключение MySqlException
     */
    public function queryArguments($query, array $arguments = []): Statement|bool
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
     * @param mixed ...$args SQL-запрос или его часть и аргументы для заполнителей
     * @return string
     * @throws MySqlException
     */
    public function prepare(mixed ...$args): string
    {
        if (!func_num_args()) {
            throw new MySqlException(sprintf(
                $this->i18n_error_messages[$this->lang][4], __METHOD__
            ));
        }

        $query = array_shift($args);

        return $this->parse($query, $args);
    }

    /**
     * Получает количество рядов, задействованных в предыдущей MySQL-операции.
     * Возвращает количество рядов, задействованных в последнем запросе INSERT, UPDATE или DELETE.
     * Если последним запросом был DELETE без оператора WHERE,
     * все записи таблицы будут удалены, но функция возвратит ноль.
     *
     * @return int
     * @see mysqli::affected_rows
     */
    public function getAffectedRows(): int
    {
        return $this->mysqli->affected_rows;
    }

    /**
     * Возвращает последний оригинальный SQL-запрос до преобразования.
     *
     * @return string|null
     */
    public function getOriginalQueryString(): ?string
    {
        return $this->original_query;
    }

    /**
     * Возвращает последний выполненный MySQL-запрос (после преобразования).
     *
     * @return string|null
     */
    public function getQueryString(): ?string
    {
        return $this->query;
    }

    /**
     * Возвращает массив со всеми исполненными SQL-запросами в рамках текущего объекта.
     *
     * @return array
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    /**
     * Возвращает id, сгенерированный предыдущей операцией INSERT.
     *
     * @return int
     * @see mysqli::insert_id
     */
    public function getLastInsertId(): int
    {
        return $this->mysqli->insert_id;
    }

    /**
     * Возвращает оригинальный объект mysqli.
     *
     * @return mysqli
     */
    public function getMysqli(): mysqli
    {
        return $this->mysqli;
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * @return string[]
     */
    public function __sleep()
    {
        return [
            'server', 'user', 'password', 'port', 'socket',
            'database_name',
            'type_mode', 'store_queries', 'query', 'original_query'
        ];
    }

    /**
     * @throws MySqlException
     */
    public function __wakeup()
    {
        $this
            ->connect()
            ->setDatabaseName($this->database_name);
    }

    /**
     * @param string|null $server
     * @param string|null $user
     * @param string|null $password
     * @param int|string|null $port
     * @param int|string|null $socket
     * @throws MySqlException
     */
    private function __construct(
        string $server = null,
        string $user = null,
        string $password = null,
        int|string|null $port = null,
        int|string|null $socket = null
    ) {
        $this->server = $server;
        $this->user = $user;
        $this->password = $password;
        $this->port = $port;
        $this->socket = $socket;

        $this->connect();
    }

    /**
     * Устанавливает соединение с базой данных.
     *
     * @return $this
     * @throws MySqlException
     * @see mysqli::connect
     */
    private function connect(): Mysql
    {
        if (is_null($this->mysqli)) {
            // Отлов в переменную $mysqli_sql_exception исключения при подключении
            // в режиме MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT
            $mysqli_sql_exception = null;

            try {
                // Подавление `собакой` вывода ошибки для режима MYSQLI_REPORT_ERROR
                $this->mysqli = @new mysqli(
                    $this->server,
                    $this->user,
                    $this->password,
                    null,
                    $this->port,
                    $this->socket
                );
            } catch (\mysqli_sql_exception $mysqli_sql_exception) {}

            // Выявляем источник данных об ошибке
            $error_code = $mysqli_sql_exception ? $mysqli_sql_exception->getCode() : $this->mysqli->connect_errno;
            $error_message = $mysqli_sql_exception ? $mysqli_sql_exception->getMessage() : $this->mysqli->connect_error;

            if ($error_code && $error_message) {
                $this->mysqli = null;
                throw new MySqlException(sprintf(
                    $this->i18n_error_messages[$this->lang][11], __METHOD__, $error_message
                ), $error_code, $mysqli_sql_exception);
            }
        }

        return $this;
    }

    /**
     * Закрывает MySQL-соединение.
     *
     * @return void
     * @see mysqli::close
     */
    private function close(): void
    {
        if (is_object($this->mysqli) && $this->mysqli instanceof mysqli) {
            $this->mysqli->close();
        }

    }

    /**
     * Возвращает экранированную строку для placeholder-а поиска LIKE (?S).
     *
     * @param string $string строка в которой необходимо экранировать спец. символы
     * @param string $chars набор символов, которые так же необходимо экранировать.
     *                      По умолчанию экранируются следующие символы: `'"%_`.
     * @return string
     */
    private function escapeLike(string $string, string $chars = "%_"): string
    {
        $string = str_replace('\\', '\\\\', $string);
        $string = $this->mysqlRealEscapeString($string);

        if ($chars) {
            $string = addCslashes($string, $chars);
        }

        return $string;
    }

    /**
     * Экранирует специальные символы в строке для использования в SQL выражении,
     * используя текущий набор символов соединения.
     *
     * @see mysqli_real_escape_string
     * @param string
     * @return string
     * @see mysqli::real_escape_string
     */
    private function mysqlRealEscapeString($value): string
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
    private function createErrorMessage(string $type, mixed $value, string $original_query): string
    {
        return sprintf($this->i18n_error_messages[$this->lang][6], $type, gettype($value), $original_query);
    }

    /**
     * Парсит запрос $query и подставляет в него аргументы из $args.
     *
     * @param string $query SQL запрос или его часть (в случае парсинга условия в скобках [])
     * @param array $args аргументы заполнителей
     * @param string|null $original_query "оригинальный", полный SQL-запрос
     * @return string SQL запрос для исполнения СУБД
     * @throws MySqlException
     */
    private function parse(string $query, array $args, ?string $original_query = null): string
    {
        $original_query = $original_query ?? $query;

        $offset = 0;

        while (($posQM = mb_strpos($query, '?', $offset)) !== false) {
            $offset = $posQM;

            $placeholder_type = mb_substr($query, $posQM + 1, 1);

            // Любые ситуации с нахождением знака вопроса, который не является заполнителем.
            if ($placeholder_type == '' || !in_array($placeholder_type, array('i', 'd', 's', 'S', 'n', 'A', 'a', 'f'))) {
                $offset += 1;
                continue;
            }

            if (!$args) {
                throw new MySqlException(sprintf(
                    $this->i18n_error_messages[$this->lang][7], __METHOD__, $original_query
                ));
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
                    $query = static::mb_substr_replace($query, $value, $posQM, 2);
                    $offset += mb_strlen($value);
                    break;

                // Integer
                // В случае установки MODE_TRANSFORM режима, преобразование происходит согласно правилам php типизации
                // http://php.net/manual/ru/language.types.integer.php#language.types.integer.casting
                // для bool, null и string типа.
                case 'i':
                    $value = $this->getValueIntType($value, $original_query);
                    $query = static::mb_substr_replace($query, $value, $posQM, 2);
                    $offset += mb_strlen($value);
                    break;

                // double
                case 'd':
                    $value = $this->getValueFloatType($value, $original_query);
                    $query = static::mb_substr_replace($query, $value, $posQM, 2);
                    $offset += mb_strlen($value);
                    break;

                // NULL insert
                case 'n':
                    $value = $this->getValueNullType($value, $original_query);
                    $query = static::mb_substr_replace($query, $value, $posQM, 2);
                    $offset += mb_strlen($value);
                    break;

                // field or table name
                case 'f':
                    $value = $this->escapeFieldName($value, $original_query);
                    $query = static::mb_substr_replace($query, $value, $posQM, 2);
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
                                throw new MySqlException(sprintf(
                                    $this->i18n_error_messages[$this->lang][8], $original_query, implode('", "', $value)
                                ));
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
                                $values = [];
                                foreach ($replacements as $key => $val) {
                                    $values[] = $this->escapeFieldName($key, $original_query) . ' = ' . $val;
                                }

                                $value = implode(',', $values);
                            } else {
                                $value = implode(', ', $replacements);
                            }

                            $query = static::mb_substr_replace(
                                $query,
                                $value,
                                $posQM,
                                4 + mb_strlen($array_parse)
                            );
                            $offset += mb_strlen($value);
                        }
                        // Выражение вида ?ai, ?as, ?ad
                        else if (preg_match('#[sid]#u', $next_char, $matches)) {
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

                            $query = static::mb_substr_replace($query, $value, $posQM, 3);
                            $offset += mb_strlen($value);
                        }
                    } else {
                        throw new MySqlException(
                            $this->i18n_error_messages[$this->lang][9]
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
     * @param string $original_query
     * @return string
     * @throws MySqlException
     */
    private function getValueStringType(mixed $value, string $original_query): string
    {
        if (!is_string($value) && $this->type_mode == self::MODE_STRICT) {
            // Если это числовой string, меняем его тип для вывода в тексте исключения его типа.
            if ($this->isInteger($value) || $this->isFloat($value)) {
                $value += 0;
            }

            throw new MySqlException(
                $this->createErrorMessage('string', $value, $original_query)
            );
        }

        // меняем поведение PHP в отношении приведения bool к string
        if (is_bool($value)) {
            return (string) (int) $value;
        }

        if (!is_string($value) && !(is_numeric($value) || is_null($value))) {
            throw new MySqlException(
                $this->createErrorMessage('string', $value, $original_query)
            );
        }

        return (string) $value;
    }

    /**
     * В зависимости от типа режима возвращает либо строковое значение числа $value,
     * приведенного к типу int, либо кидает исключение.
     *
     * @param mixed $value
     * @param string $original_query
     * @return mixed
     * @throws MySqlException
     */
    private function getValueIntType(mixed $value, string $original_query): mixed
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
                throw new MySqlException(
                    $this->createErrorMessage('integer', $value, $original_query)
                );
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
     * @param string $original_query
     * @return mixed
     * @throws MySqlException
     */
    private function getValueFloatType(mixed $value, string $original_query): mixed
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
                throw new MySqlException(
                    $this->createErrorMessage('double', $value, $original_query)
                );
        }
    }

    /**
     * В зависимости от типа режима возвращает либо строковое значение 'NULL',
     * либо кидает исключение.
     *
     * @param mixed $value
     * @param string $original_query
     * @return string
     * @throws MySqlException
     */
    private function getValueNullType(mixed $value, string $original_query): string
    {
        if ($value !== null && $this->type_mode == self::MODE_STRICT) {
            // Если это числовой string, меняем его тип для вывода в тексте исключения его типа.
            if ($this->isInteger($value) || $this->isFloat($value)) {
                $value += 0;
            }

            throw new MySqlException(
                $this->createErrorMessage('NULL', $value, $original_query)
            );
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
     * @return array
     * @throws MySqlException
     */
    private function getValueArrayType(mixed $value, string $original_query): array
    {
        if (!is_array($value)) {
            throw new MySqlException(
                $this->createErrorMessage('array', $value, $original_query)
            );
        }

        return $value;
    }

    /**
     * Экранирует имя поля таблицы или столбца.
     *
     * @param mixed $value
     * @param string $original_query
     * @return string
     * @throws MySqlException
     */
    private function escapeFieldName(mixed $value, string $original_query): string
    {
        if (!is_string($value)) {
            throw new MySqlException(
                $this->createErrorMessage('field', $value, $original_query)
            );
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
                        throw new MySqlException(
                            $this->i18n_error_messages[$this->lang][10]
                        );
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
     * @param mixed $val
     * @return boolean
     */
    private function isInteger(mixed $val): bool
    {
        if (!is_scalar($val) || is_bool($val)) {
            return false;
        }

        return !$this->isFloat($val) && preg_match('~^((?:\+|-)?[0-9]+)$~', $val) === 1;
    }

    /**
     * Проверяет, является ли значение числом с плавающей точкой.
     *
     * @param mixed $val
     * @return boolean
     */
    private function isFloat(mixed $val): bool
    {
        if (!is_scalar($val) || is_bool($val)) {
            return false;
        }

        return gettype($val) === "double" || preg_match("/^([+-]*\\d+)*\\.(\\d+)*$/", $val) === 1;
    }

    /**
     * Заменяет часть строки string, начинающуюся с символа с порядковым номером start
     * и (необязательной) длиной length, строкой replacement и возвращает результат.
     *
     * @see substr_replace
     * @param $string
     * @param $replacement
     * @param $start
     * @param null $length
     * @param null $encoding
     * @return string
     */
    private static function mb_substr_replace($string, $replacement, $start, $length = null, $encoding = null): string
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