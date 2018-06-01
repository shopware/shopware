<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Doctrine;

use PDO;

class DatabaseConnector
{
    public static function createPdoConnection()
    {
        $url = parse_url(getenv('DATABASE_URL'));
        $dsn = sprintf(
            '%s:dbname=%s;host=%s;port=%d;charset=utf8mb4',
            $url['scheme'],
            ltrim($url['path'], '/'),
            $url['host'],
            $url['port']
        );

        $pass = getenv('DATABASE_PW');
        if (empty($pass)) {
            $pass = $url['pass'];
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        try {
            return new PDO($dsn, $url['user'], $pass, $options);
        } catch (\PDOException $e) {
            throw new \RuntimeException('Could not connect to database.' . $e->getMessage(), $e->getCode());
        }
    }
}
