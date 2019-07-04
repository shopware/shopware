<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Checkers;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Update\Struct\ValidationResult;

class MysqlVersionCheck implements CheckerInterface
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function supports(string $check): bool
    {
        return $check === 'mysqlversion';
    }

    /**
     * @param int|string|array $values
     */
    public function check($values): ValidationResult
    {
        $currentVersion = $this->connection->fetchColumn('SELECT VERSION()');

        $validVersion = (version_compare($currentVersion, $values) >= 0);
        $vars = ['minVersion' => $values, 'currentVersion' => $currentVersion];

        if ($validVersion) {
            return new ValidationResult('mysqlVersion', self::VALIDATION_SUCCESS, 'mysqlVersion', $vars);
        }

        return new ValidationResult('mysqlVersion', self::VALIDATION_ERROR, 'mysqlVersion', $vars);
    }
}
