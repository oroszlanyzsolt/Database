<?php

use PDO;
use PDOException;
use Exception;
use stdClass;

class Database
{
    private $connection;

    private $statement;

    public function __construct(
        $hostname,
        $username,
        $password,
        $database = null,
        $port = 3306
    ) {
        if (false === extension_loaded('PDO')) {
            throw new Exception('PDO extension is either not installed or not enabled');
        }

        if (false === extension_loaded('pdo_mysql')) {
            throw new Exception('pdo_mysql extension is either not installed or not enabled');
        }

        try {
            if (null === $database) {
                $dsn = sprintf('mysql:host=%s;port=%d;charset=%s', $hostname, $port, 'utf8mb4');
            } else {
                $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $hostname, $port, $database, 'utf8mb4');
            }

            $this->connection = new PDO(
                $dsn,
                $username,
                $password,
                [
                    PDO::ATTR_TIMEOUT => 10,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_PERSISTENT => false
                ]
            );
        } catch (PDOException $exc) {
            throw new Exception(sprintf(
                'Failed to connect to database. Reason: %s',
                iconv(iconv_get_encoding($exc->getMessage()), 'UTF-8', $exc->getMessage())
            ));
        }

        if ($this->connection instanceof PDO) {
            $this->connection->exec('SET NAMES \'utf8mb4\'');
            $this->connection->exec('SET CHARACTER SET utf8mb4');
            $this->connection->exec('SET CHARACTER_SET_CONNECTION=utf8mb4');
            $this->connection->exec('SET SQL_MODE = \'\'');
            $this->connection->exec('SET time_zone = \'+00:00\'');
        }
    }

    public function prepare($sql)
    {
        $this->statement = $this->connection->prepare($sql);
    }

    public function bindParam($parameter, $variable, $data_type = PDO::PARAM_STR, $length = 0)
    {
        if ($length > 0) {
            $this->statement->bindParam($parameter, $variable, $data_type, $length);
        } else {
            $this->statement->bindParam($parameter, $variable, $data_type);
        }
    }

    public function execute()
    {
        $result = false;

        try {
            if (null !== $this->statement && true === $this->statement->execute($params)) {
                $data = [];

                while ($row = $this->statement->fetch(PDO::FETCH_ASSOC)) {
                    $data[] = $row;
                }

                $result = new stdClass();

                if (true === isset($data[0])) {
                    $result->row = $data[0];
                } else {
                    $result->row = [];
                }

                $result->rows = $data;
                $result->num_rows = $this->statement->rowCount();
            }
        } catch (PDOException $exc) {
            throw new Exception($exc->getMessage());
        }

        if (false !== $result) {
            return $result;
        } else {
            $result = new stdClass();
            $result->row = [];
            $result->rows = [];
            $result->num_rows = 0;

            return $result;
        }
    }

    public function query($sql, $params = [])
    {
        $result = false;

        try {
            $this->statement = $this->connection->prepare($sql);

            if (null !== $this->statement && true === $this->statement->execute($params)) {
                $data = [];

                while ($row = $this->statement->fetch(PDO::FETCH_ASSOC)) {
                    $data[] = $row;
                }

                $result = new stdClass();

                if (true === isset($data[0])) {
                    $result->row = $data[0];
                } else {
                    $result->row = [];
                }

                $result->rows = $data;
                $result->num_rows = $this->statement->rowCount();
            }
        } catch (PDOException $exc) {
            $backtrace = debug_backtrace();
            $key = array_search(__FUNCTION__, array_column($backtrace, 'function'));

            trigger_error('<p>Error in: ' . $backtrace[$key]['file'] . ', on line: ' . $backtrace[$key]['line'] . '<br><pre>' . $sql . '</pre><br><pre>' . var_export($params, true) . '</pre>' . '</p>', E_USER_ERROR);
        }

        if (false !== $result) {
            return $result;
        } else {
            $result = new stdClass();
            $result->row = [];
            $result->rows = [];
            $result->num_rows = 0;

            return $result;
        }
    }

    public function countAffected()
    {
        if (null !== $this->statement) {
            return $this->statement->rowCount();
        }

        return 0;
    }

    public function getLastId()
    {
        return $this->connection->lastInsertId();
    }

    public function isConnected()
    {
        return $this->connection !== null;
    }

    public function __destruct()
    {
        $this->connection = null;
    }
}
