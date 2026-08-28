<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoLegacySqlHashFunctionRule;

use Doctrine\DBAL\Connection;

class LegacySqlHashUsage
{
    public function validate(Connection $connection): void
    {
        $connection->executeStatement('UPDATE product SET display_group = SHA2(HEX(id), 256)');

        $connection->executeStatement('UPDATE product SET display_group = MD5(HEX(id))');

        $legacyQuery = 'SELECT SHA1(email) FROM customer';
        $connection->executeQuery($legacyQuery);

        $connection->prepare('SELECT MD5(HEX(id)) FROM product');
    }
}
