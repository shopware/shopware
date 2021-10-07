<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\System\Struct;

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Maintenance\System\Exception\DatabaseSetupException;

class DatabaseConnectionInformation extends Struct
{
    protected string $hostname = '';

    protected int $port = 3306;

    protected ?string $username;

    protected ?string $password;

    protected string $databaseName = '';

    protected ?string $sslCaPath = null;

    protected ?string $sslCertPath = null;

    protected ?string $sslCertKeyPath = null;

    protected ?bool $sslDontVerifyServerCert = null;

    public static function fromEnv(): self
    {
        $dsn = trim((string) (EnvironmentHelper::getVariable('DATABASE_URL', getenv('DATABASE_URL'))));
        if ($dsn === '') {
            throw new DatabaseSetupException('Environment variable \'DATABASE_URL\' not defined.');
        }

        $params = parse_url($dsn);
        if ($params === false) {
            throw new DatabaseSetupException('Environment variable \'DATABASE_URL\' does not contain a valid dsn.');
        }

        $path = $params['path'] ?? '/';
        $dbName = substr($path, 1);
        if (!isset($params['scheme']) || !isset($params['host']) || trim($dbName) === '') {
            throw new DatabaseSetupException('Environment variable \'DATABASE_URL\' does not contain a valid dsn.');
        }

        return (new self())->assign([
            'hostname' => $params['host'],
            'port' => (int) ($params['port'] ?? '3306'),
            'username' => $params['user'] ?? null,
            'password' => $params['pass'] ?? null,
            'databaseName' => $dbName,
            'sslCaPath' => EnvironmentHelper::getVariable('DATABASE_SSL_CA'),
            'sslCertPath' => EnvironmentHelper::getVariable('DATABASE_SSL_CERT'),
            'sslCertKeyPath' => EnvironmentHelper::getVariable('DATABASE_SSL_KEY'),
            'sslDontVerifyServerCert' => EnvironmentHelper::getVariable('DATABASE_SSL_DONT_VERIFY_SERVER_CERT'),
        ]);
    }

    public function toDBALParameters(bool $withoutDatabaseName = false): array
    {
        $parameters = [
            'url' => $this->asDsn($withoutDatabaseName),
            'charset' => 'utf8mb4',
            'driverOptions' => [
                \PDO::ATTR_STRINGIFY_FETCHES => true,
            ],
        ];

        if ($this->sslCaPath) {
            $parameters['driverOptions'][\PDO::MYSQL_ATTR_SSL_CA] = $this->sslCaPath;
        }

        if ($this->sslCertPath) {
            $parameters['driverOptions'][\PDO::MYSQL_ATTR_SSL_CERT] = $this->sslCertPath;
        }

        if ($this->sslCertKeyPath) {
            $parameters['driverOptions'][\PDO::MYSQL_ATTR_SSL_KEY] = $this->sslCertKeyPath;
        }

        if ($this->sslDontVerifyServerCert) {
            $parameters['driverOptions'][\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }

        return $parameters;
    }

    public function asDsn(bool $withoutDatabaseName = false): string
    {
        $dsn = sprintf(
            'mysql://%s%s:%s',
            $this->username && $this->password ? ($this->username . ':' . $this->password . '@') : '',
            $this->hostname,
            $this->port
        );

        if (!$withoutDatabaseName) {
            $dsn .= '/' . $this->databaseName;
        }

        return $dsn;
    }

    public function getDatabaseName(): string
    {
        return $this->databaseName;
    }

    public function setDatabaseName(string $databaseName): void
    {
        $this->databaseName = $databaseName;
    }

    public function getHostname(): string
    {
        return $this->hostname;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getSslCaPath(): ?string
    {
        return $this->sslCaPath;
    }

    public function getSslCertPath(): ?string
    {
        return $this->sslCertPath;
    }

    public function getSslCertKeyPath(): ?string
    {
        return $this->sslCertKeyPath;
    }

    public function getSslDontVerifyServerCert(): ?bool
    {
        return $this->sslDontVerifyServerCert;
    }
}
