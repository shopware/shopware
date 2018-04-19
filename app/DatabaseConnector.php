<?php

use Symfony\Component\Yaml\Yaml;

class DatabaseConnector
{
    public static function connect(string $kernelRootDir, string $environment)
    {
        return self::createPDO(
            self::readConfig($kernelRootDir, $environment)
        );
    }

    private static function readConfig(string $kernelRootDir, string $environment): array
    {
        $file = $kernelRootDir . '/config/config_' . $environment . '.yml';
        $config = Yaml::parse(file_get_contents($file));

        if (!empty($config['parameters']['database_host'])) {
            return $config['parameters'];
        }

        $config = Yaml::parse(file_get_contents($kernelRootDir . '/config/parameters.yml'));
        return $config['parameters'];
    }

    /**
     * @param array $config
     *
     * @return \PDO
     */
    private static function createPDO(array $config)
    {
        $password = $config['database_password'] ?? '';
        $connectionString = self::buildConnectionString($config);

        try {
            $conn = new \PDO(
                'mysql:' . $connectionString,
                $config['database_user'],
                $password
            );

            $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $conn->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

            // Reset sql_mode "STRICT_TRANS_TABLES" that will be default in MySQL 5.6
            $conn->exec('SET @@session.sql_mode = ""');

        } catch (\PDOException $e) {
            $message = $e->getMessage();
            $message = str_replace(
                [
                    $config['database_user'],
                    $config['database_password'],
                ],
                '******',
                $message
            );

            throw new \RuntimeException('Could not connect to database. Message from SQL Server: ' . $message, $e->getCode());
        }

        return $conn;
    }

    /**
     * @param array $config
     *
     * @return string
     */
    private static function buildConnectionString(array $config)
    {
        if (!isset($config['database_host']) || empty($config['database_host'])) {
            $config['database_host'] = 'localhost';
        }

        $settings = [
            'host=' . $config['database_host'],
        ];

        if (!empty($config['database_socket'])) {
            $settings[] = 'unix_socket=' . $config['database_socket'];
        }

        if (!empty($config['database_port'])) {
            $settings[] = 'port=' . $config['database_port'];
        }

        if (!empty($config['database_charset'])) {
            $settings[] = 'charset=' . $config['database_charset'];
        }

        if (!empty($config['database_name'])) {
            $settings[] = 'dbname=' . $config['database_name'];
        }

        return implode(';', $settings);
    }
}