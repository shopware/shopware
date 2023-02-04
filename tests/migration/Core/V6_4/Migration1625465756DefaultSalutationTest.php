<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1625465756DefaultSalutation as MigrationTested;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1625465756DefaultSalutation
 */
class Migration1625465756DefaultSalutationTest extends TestCase
{
    use MigrationTestTrait;

    private const DEFAULT_SALUTATION_ID = 'ed643807c9f84cc8b50132ea3ccb1c3b';

    public function setUp(): void
    {
        parent::setUp();

        $connection = KernelLifecycleManager::getConnection();

        (new MigrationTested())->update($connection);
    }

    public function testDefaultSalutationIsCreated(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $salutation = $connection->fetchOne('SELECT `salutation_key` FROM `salutation` WHERE `id` = :id', ['id' => Uuid::fromHexToBytes(self::DEFAULT_SALUTATION_ID)]);

        static::assertSame(MigrationTested::SALUTATION_KEY, $salutation);
    }

    public function testDefaultSalutationTranslationsAreCreated(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $translations = $connection->fetchAllAssociative('SELECT * FROM `salutation_translation` WHERE `salutation_id` = :id', ['id' => Uuid::fromHexToBytes(self::DEFAULT_SALUTATION_ID)]);

        static::assertCount(2, $translations);

        foreach ($translations as $translation) {
            static::assertEmpty($translation['letter_name']);

            if ($translation['language_id'] === Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)) {
                static::assertEquals(MigrationTested::SALUTATION_DISPLAY_NAME_EN, $translation['display_name']);
            } else {
                static::assertEquals(MigrationTested::SALUTATION_DISPLAY_NAME_DE, $translation['display_name']);
            }
        }
    }
}
