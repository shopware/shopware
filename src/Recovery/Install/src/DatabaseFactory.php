<?php declare(strict_types=1);

namespace Shopware\Recovery\Install;

use Shopware\Recovery\Install\Struct\DatabaseConnectionInformation;

class DatabaseFactory
{
    /**
     * @throws \Exception
     * @throws \PDOException
     *
     * @return \PDO
     */
    public function createPDOConnection(DatabaseConnectionInformation $info)
    {
        $parameters = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
        ];

        if ($info->sslCaPath) {
            $parameters[\PDO::MYSQL_ATTR_SSL_CA] = $info->sslCaPath;
        }

        if ($info->sslCertPath) {
            $parameters[\PDO::MYSQL_ATTR_SSL_CERT] = $info->sslCertPath;
        }

        if ($info->sslCertKeyPath) {
            $parameters[\PDO::MYSQL_ATTR_SSL_KEY] = $info->sslCertKeyPath;
        }

        if ($info->sslDontVerifyServerCert === true) {
            $parameters[\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }

        $conn = new \PDO(
            $this->buildDsn($info),
            $info->username,
            $info->password,
            $parameters
        );

        $this->setNonStrictSQLMode($conn);
        $this->setDefaultStorageEngine($conn);

        $this->checkVersion($conn);
        $this->checkEngineSupport($conn);
        $this->checkSQLMode($conn);

        return $conn;
    }

    protected function setNonStrictSQLMode(\PDO $conn): void
    {
        $conn->exec("SET @@session.sql_mode = ''");
    }

    protected function setDefaultStorageEngine(\PDO $conn): void
    {
        $conn->exec('SET default_storage_engine=InnoDB');
    }

    private function buildDsn(DatabaseConnectionInformation $info): string
    {
        if (!empty($info->socket)) {
            $connectionString = 'unix_socket=' . $info->socket . ';';
        } else {
            $connectionString = 'host=' . $info->hostname . ';';
            if (!empty($info->port)) {
                $connectionString .= 'port=' . $info->port . ';';
            }
        }

        if ($info->databaseName) {
            $connectionString .= 'dbname=' . $info->databaseName . ';';
        }

        return 'mysql:' . $connectionString;
    }

    private function hasStorageEngine(string $engineName, \PDO $conn): bool
    {
        $sql = 'SHOW ENGINES;';
        $allEngines = $conn->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($allEngines as $engine) {
            if ($engine['Engine'] === $engineName) {
                $support = $engine['Support'];

                return $support === 'DEFAULT' || $support === 'YES';
            }
        }

        return false;
    }

    /**
     * @throws \RuntimeException
     */
    private function checkVersion(\PDO $conn): void
    {
        $sql = 'SELECT VERSION()';

        $mysqlRequiredVersion = '5.7.21';
        $mariaDBRequiredVersion = '10.3.0';

        $version = $conn->query($sql)->fetchColumn();
        if (mb_stripos($version, 'mariadb') !== false) {
            if (version_compare($version, $mariaDBRequiredVersion, '<')) {
                throw new \RuntimeException(sprintf(
                    'Database error!: Your database server is running MariaDB %s, but Shopware 6 requires at least MariaDB %s OR MySQL %s',
                    $version,
                    $mariaDBRequiredVersion,
                    $mysqlRequiredVersion
                ));
            }

            return;
        }

        if (version_compare($version, $mysqlRequiredVersion, '<')) {
            throw new \RuntimeException(sprintf(
                'Database error!: Your database server is running MySQL %s, but Shopware 6 requires at least MySQL %s OR MariabDB %s',
                $version,
                $mysqlRequiredVersion,
                $mariaDBRequiredVersion
            ));
        }
    }

    /**
     * @throws \RuntimeException
     */
    private function checkEngineSupport(\PDO $conn): void
    {
        $hasEngineSupport = $this->hasStorageEngine('InnoDB', $conn);
        if (!$hasEngineSupport) {
            throw new \RuntimeException('Database error!: The MySQL storage engine InnoDB not found. Please consult your hosting provider to solve this problem.');
        }
    }

    /**
     * @throws \RuntimeException
     */
    private function checkSQLMode(\PDO $conn): void
    {
        $sql = 'SELECT @@SESSION.sql_mode;';
        $result = $conn->query($sql)->fetchColumn(0);

        if (mb_strpos($result, 'STRICT_TRANS_TABLES') !== false || mb_strpos($result, 'STRICT_ALL_TABLES') !== false) {
            throw new \RuntimeException("Database error!: The MySQL strict mode is active ($result). Please consult your hosting provider to solve this problem.");
        }
    }
}
