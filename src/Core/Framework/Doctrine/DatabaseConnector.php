<?php declare(strict_types=1);

namespace Shopware\Framework\Doctrine;

class DatabaseConnector
{
    public static function createPdoConnection()
    {
        $url = parse_url(getenv('DATABASE_URL'));
        $dsn = sprintf(
            '%s:dbname=%s;host=%s;port=%d',
            $url['scheme'],
            ltrim($url['path'], '/'),
            $url['host'],
            $url['port']
        );

        try {
            $conn = new \PDO($dsn, $url['user'], $url['pass']);
            $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $conn->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
            $conn->exec('SET NAMES utf8mb4');
        } catch (\PDOException $e) {
            throw new \RuntimeException('Could not connect to database.' . $e->getMessage(), $e->getCode());
        }

        return $conn;
    }
}
