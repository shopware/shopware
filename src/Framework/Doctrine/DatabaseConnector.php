<?php

namespace Shopware\Framework\Doctrine;

class DatabaseConnector
{
    public static function createPdoConnection()
    {
        $password = getenv('DATABASE_PASSWORD') ?? '';
        $connectionString = self::buildConnectionString();

        try {
            $conn = new \PDO('mysql:' . $connectionString, getenv('DATABASE_USER'), $password);
            $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $conn->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            $message = $e->getMessage();
            $message = str_replace(
                [
                    getenv('DATABASE_USER'),
                    getenv('DATABASE_PASSWORD'),
                ],
                '******',
                $message
            );

            throw new \RuntimeException('Could not connect to database. Message from SQL Server: ' . $message, $e->getCode());
        }

        return $conn;
    }

    private static function buildConnectionString(): string
    {
        $settings = [
            'host=' . getenv('DATABASE_HOST') ?? 'localhost',
        ];

        if (getenv('DATABASE_SOCKET')) {
            $settings[] = 'unix_socket=' . getenv('DATABASE_SOCKET');
        }

        if (getenv('DATABASE_PORT')) {
            $settings[] = 'port=' . getenv('DATABASE_PORT');
        }

        if (getenv('DATABASE_CHARSET')) {
            $settings[] = 'charset=' . getenv('DATABASE_CHARSET');
        }

        if (getenv('DATABASE_NAME')) {
            $settings[] = 'dbname=' . getenv('DATABASE_NAME');
        }

        return implode(';', $settings);
    }
}
