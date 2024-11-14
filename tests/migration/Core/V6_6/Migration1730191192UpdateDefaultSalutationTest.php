<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_6\Migration1730191192UpdateDefaultSalutation;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(Migration1730191192UpdateDefaultSalutation::class)]
class Migration1730191192UpdateDefaultSalutationTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1730191192UpdateDefaultSalutation();
        static::assertSame(1730191192, $migration->getCreationTimestamp());
    }

    public function testNotUpdateWithUpdatedSalutation(): void
    {
        $salutationId = $this->connection->fetchOne('SELECT id FROM salutation WHERE salutation_key = "not_specified"');

        $this->connection->executeStatement(
            'UPDATE salutation_translation SET letter_name = :letterName, updated_at = NOW() WHERE salutation_id = :salutationId',
            ['letterName' => 'test', 'salutationId' => $salutationId]
        );

        $migration = new Migration1730191192UpdateDefaultSalutation();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $salutationLetters = $this->connection->fetchAllAssociative(
            'SELECT letter_name FROM salutation_translation WHERE salutation_id = :salutationId',
            ['salutationId' => $salutationId]
        );
        $salutationLetters = array_column($salutationLetters, 'letter_name');

        foreach ($salutationLetters as $letter) {
            static::assertSame('test', $letter);
        }
    }

    public function testUpdate(): void
    {
        $migration = new Migration1730191192UpdateDefaultSalutation();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $salutationId = $this->connection->fetchOne('SELECT id FROM salutation WHERE salutation_key = "not_specified"');
        $salutationLetters = $this->connection->fetchAllKeyValue(
            'SELECT locale.code, letter_name
             FROM salutation_translation
             JOIN language ON language.id = salutation_translation.language_id
             JOIN locale ON language.locale_id = locale.id
             WHERE salutation_id = :salutationId',
            ['salutationId' => $salutationId]
        );

        static::assertSame('Dear', $salutationLetters['en-GB']);
        static::assertSame('Guten Tag', $salutationLetters['de-DE']);
    }
}
