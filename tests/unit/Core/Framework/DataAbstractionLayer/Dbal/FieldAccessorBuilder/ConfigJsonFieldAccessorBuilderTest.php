<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\ConfigJsonFieldAccessorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ConfigJsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ConfigJsonFieldAccessorBuilder::class)]
class ConfigJsonFieldAccessorBuilderTest extends TestCase
{
    public function testCastValueResolvesTheFieldBelowTheStorageKey(): void
    {
        $builder = new ConfigJsonFieldAccessorBuilder(static::createStub(Connection::class));

        $field = new ConfigJsonField('config', 'config', [new DateField('date', 'date')]);

        static::assertSame('CAST(:value AS DATE)', $builder->castValue(':value', $field, 'config.date'));
    }

    public function testCastValueKeepsTheValueOfOtherJsonFields(): void
    {
        $builder = new ConfigJsonFieldAccessorBuilder(static::createStub(Connection::class));

        $field = new JsonField('config', 'config', [new DateField('date', 'date')]);

        static::assertSame(':value', $builder->castValue(':value', $field, 'config.date'));
    }
}
