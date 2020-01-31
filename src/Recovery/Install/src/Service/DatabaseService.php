<?php declare(strict_types=1);

namespace Shopware\Recovery\Install\Service;

class DatabaseService
{
    /**
     * @var \PDO
     */
    private $connection;

    public function __construct(\PDO $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Create the database (or alter it if it already exists)
     *
     * @param string $name      Database name
     * @param string $charset   Database charset (default utf8)
     * @param string $collation Database collation (default utf8_unicode_ci)
     *
     * @throws \InvalidArgumentException width code 1 if the charset parameter is incorrect
     * @throws \InvalidArgumentException width code 2 if the collation parameter is incorrect
     * @throws \RuntimeException         If the selected user has insufficient privileges to CREATE/ALTER the database
     *
     * @return int Amount of affected rows by the \PDO::exec method
     */
    public function createDatabase(string $name, string $charset = 'utf8mb4', string $collation = 'utf8mb4_unicode_ci'): int
    {
        $dbExists = $this->databaseExists($name);

        /**
         * Filter parameters
         */
        $regex = '/[^A-Za-z0-9_-]/';
        $charset = preg_replace($regex, '', trim($charset));
        $collation = preg_replace($regex, '', trim($collation));

        if (empty($charset)) {
            throw new \InvalidArgumentException('Must specify charset', 1);
        }

        if (empty($collation)) {
            throw new \InvalidArgumentException('Must specify collation', 2);
        }

        $isSuper = $this->isSuperUser();

        /*
         * If the current user is not a super user and the database doesn't exists and we don't have the CREATE
         * privilege throw a \RuntimeException
         */

        if (!$isSuper && !$dbExists) {
            try {
                $this->checkPrivilegeOnSchema($name, 'CREATE');
            } catch (\Exception $e) {
                $msg = "Database \"$name\" does not exists, additionally you have no privilege to create said database";

                throw new \RuntimeException($msg);
            }
        }

        /*
         * If the current user is not a super user and the database exists and we don't have the ALTER privilege
         * throw a \RuntimeException
         */

        if (!$isSuper && $dbExists) {
            try {
                $this->checkPrivilegeOnSchema($name, 'ALTER');
            } catch (\Exception $e) {
                $msg = "Your user has not enough privileges for database \"$name\" the ALTER privilege is required";

                throw new \RuntimeException($msg);
            }
        }

        return $this->connection->exec(
            sprintf(
                '%s DATABASE `%s` CHARACTER SET `%s` COLLATE `%s`',
                $dbExists ? 'ALTER' : 'CREATE',
                $name,
                $charset,
                $collation
            )
        );
    }

    /**
     * Check if a database exists
     *
     * @param string $databaseName Database name
     *
     * @return bool true Database exists
     * @return bool false Database does not exists
     */
    public function databaseExists($databaseName)
    {
        $sql = 'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=:name';
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':name', $databaseName);
        $stmt->execute();

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Check if the user at the database connection has certain permissions for the selected database
     *
     * @param string       $schema      name of the schema/database
     * @param string|array $permissions A string with the permission to check or an array with permissions to check
     *
     * @throws \InvalidArgumentException If any of the passed permissions is incorrect
     * @throws \LogicException           if the permission was not found
     */
    public function checkPrivilegeOnSchema(string $schema, $permissions): void
    {
        $schema = trim($schema);

        if (empty($schema)) {
            throw new \InvalidArgumentException('Schema name can not be empty');
        }

        if (!is_string($permissions) && !is_array($permissions)) {
            $msg = sprintf('String or array expected, got: %s', gettype($permissions));

            throw new \InvalidArgumentException($msg);
        }

        if (!is_array($permissions)) {
            $permissions = [$permissions];
        }

        if (empty($permissions)) {
            throw new \InvalidArgumentException('No permissions passed to be checked');
        }

        /*
         * Check permissions one by one, in this way we can determine easily which permission fails
         * and throw an InvalidArgumentException in case any of the permission inside the
         * array are not a string.
         */

        foreach ($permissions as $key => $permission) {
            if (!is_string($permission)) {
                throw new \InvalidArgumentException("At array key $key: Argument is not a string");
            }

            $permission = trim($permission);

            if (empty($permission)) {
                throw new \InvalidArgumentException("At array key $key: Given permission can not be an empty string");
            }

            $sql = <<<'EOL'
SELECT PRIVILEGE_TYPE, REPLACE(GRANTEE,"'","") AS _grantee
FROM information_schema.SCHEMA_PRIVILEGES
WHERE PRIVILEGE_TYPE=:priv AND TABLE_SCHEMA=:name
HAVING _grantee = USER()
OR _grantee=CONCAT(SUBSTRING_INDEX(USER(),'@',1),'@%')
EOL;

            $stmt = $this->connection->prepare($sql);

            $stmt->bindValue(':priv', $permission);
            $stmt->bindValue(':name', $schema);

            $stmt->execute();

            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$result) {
                throw new \LogicException("Current user has no \"$permission\" on the selected schema: \"$schema\"");
            }
        }
    }

    /**
     * Check if the current user is a super user
     *
     * @see self::checkUserPrivileges
     *
     * @throws \Exception
     *
     * @return bool true The current user is a super user
     */
    public function isSuperUser(): bool
    {
        try {
            $this->checkUserPrivileges('SUPER');

            return true;
        } catch (\LogicException $e) {
            return false;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Check privileges on the current given MySQL user
     *
     * @param string|array $privileges A string (to check for one privilege) or an array (to check for multiple)
     *
     * @throws \LogicException if the privilege has not been found
     */
    public function checkUserPrivileges($privileges): void
    {
        if (!is_string($privileges) && !is_array($privileges)) {
            $msg = sprintf('String or array expected, got: %s', gettype($privileges));

            throw new \InvalidArgumentException($msg);
        }

        if (!is_array($privileges)) {
            $privileges = [$privileges];
        }

        if (empty($privileges)) {
            throw new \InvalidArgumentException('No privileges passed to be checked');
        }

        foreach ($privileges as $key => $privilege) {
            if (!is_string($privilege)) {
                throw new \InvalidArgumentException("At array key $key: Argument is not a string");
            }

            $privilege = trim($privilege);

            if (empty($privilege)) {
                throw new \InvalidArgumentException("At array key $key: Given privilege can not be an empty string");
            }

            $sql = <<<'EOL'
SELECT REPLACE(GRANTEE,"'","") AS _grantee, PRIVILEGE_TYPE
FROM information_schema.USER_PRIVILEGES WHERE PRIVILEGE_TYPE = :privilege
HAVING _grantee = USER()
OR _grantee=CONCAT(SUBSTRING_INDEX(USER(),'@',1),'@%')
EOL;

            $stmt = $this->connection->prepare($sql);
            $stmt->bindValue(':privilege', $privilege);
            $stmt->execute();

            if (!$stmt->fetch(\PDO::FETCH_ASSOC)) {
                throw new \LogicException("Current MySQL user has no privilege named \"$privilege\"");
            }
        }
    }

    public function selectDatabase(string $databaseName): void
    {
        $this->connection->exec("USE `$databaseName`");
    }

    public function getTableCount(): int
    {
        $tables = $this->connection->query('SHOW TABLES')->fetchAll();

        return count($tables);
    }

    /**
     * Fetches schema names, the possibility of omitting certain schema names is also given.
     *
     * @param array $omit Omit certain schema names (for instance, performance_schema, mysql, etc)
     *
     * @return array array of available schema names
     */
    public function getSchemas(array $omit = []): array
    {
        $sql = 'SELECT SCHEMA_NAME AS name FROM information_schema.SCHEMATA';

        if ($omit) {
            // IN parameters string creation (?,?,?,?)
            $in = mb_substr(str_repeat('?,', count($omit)), 0, -1);
            $sql .= " WHERE SCHEMA_NAME NOT IN($in)";
        }

        $stmt = $this->connection->prepare($sql);

        foreach ($omit as $key => $value) {
            $stmt->bindValue($key + 1, $value);
        }

        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function hasTables(string $databaseName): bool
    {
        if (!$this->databaseExists($databaseName)) {
            return false;
        }

        $this->connection->exec('USE `' . $databaseName . '`');
        $tables = $this->connection
            ->query('SHOW TABLES')
            ->fetchAll(\PDO::FETCH_COLUMN);

        return !empty($tables);
    }

    /**
     * @return bool
     */
    public function containsShopwareSchema(): ?bool
    {
        try {
            $this->connection->query('SELECT * FROM migrations')->fetchAll();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
