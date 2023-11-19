<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Sync;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Api\Sync\AbstractFkResolver;
use Shopware\Core\Framework\Api\Sync\SyncFkResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\Tax\TaxDefinition;
use Shopware\Tests\Unit\Common\Stubs\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Api\Sync\SyncFkResolver
 */
class SyncFkResolverTest extends TestCase
{
    public function testResolveWithDummy(): void
    {
        $payload = [
            // many-to-one-case
            'taxId' => ['resolver' => 'dummy', 'value' => 't1'],

            // many-to-many-case
            'categories' => [
                ['id' => ['resolver' => 'dummy', 'value' => 'c1']],
                ['id' => ['resolver' => 'dummy', 'value' => 'c2']],
            ],

            // nesting case
            'visibilities' => [
                ['visibility' => 1, 'salesChannelId' => ['resolver' => 'dummy', 'value' => 's1']],
            ],
        ];

        $resolver = new SyncFkResolver(
            new StaticDefinitionInstanceRegistry(
                [
                    ProductDefinition::class,
                    TaxDefinition::class,
                    ProductVisibilityDefinition::class,
                    SalesChannelDefinition::class,
                    CategoryDefinition::class,
                    ProductCategoryDefinition::class,
                ],
                $this->createMock(ValidatorInterface::class),
                $this->createMock(EntityWriteGatewayInterface::class)
            ),
            [new DummyFkResolver()]
        );

        $resolved = $resolver->resolve('product', [$payload]);

        $expected = [
            'taxId' => 't1',
            'categories' => [
                ['id' => 'c1'],
                ['id' => 'c2'],
            ],
            'visibilities' => [
                ['visibility' => 1, 'salesChannelId' => 's1'],
            ],
        ];

        static::assertCount(1, $resolved);
        static::assertEquals($expected, $resolved[0]);
    }

    public function testFailResolving(): void
    {
        $resolver = new SyncFkResolver(
            new StaticDefinitionInstanceRegistry(
                [
                    ProductDefinition::class,
                    TaxDefinition::class,
                ],
                $this->createMock(ValidatorInterface::class),
                $this->createMock(EntityWriteGatewayInterface::class)
            ),
            [new DummyFkResolver(), new FailingFkResolver()]
        );

        $payload = [
            'taxId' => [
                'resolver' => 'failing',
                'value' => 't1',
                'nullOnMissing' => true,
            ],
        ];
        $resolved = $resolver->resolve('product', [$payload]);

        static::assertEquals([
            [
                'taxId' => null,
            ],
        ], $resolved);

        $payload = [
            'taxId' => [
                'resolver' => 'failing',
                'value' => 't1',
            ],
        ];

        try {
            $resolver->resolve('product', [$payload]);
            static::fail('Failed to fail a taxId lookup');
        } catch (EntityNotFoundException $exception) {
            static::assertSame('tax', $exception->getParameter('entity'));
            static::assertSame('t1', $exception->getParameter('identifier'));
        }
    }
}

/**
 * @internal
 */
class DummyFkResolver extends AbstractFkResolver
{
    public static function getName(): string
    {
        return 'dummy';
    }

    public function resolve(array $map): array
    {
        foreach ($map as $value) {
            $value->resolved = $value->value;
        }

        return $map;
    }
}

/**
 * @internal
 */
class FailingFkResolver extends AbstractFkResolver
{
    public static function getName(): string
    {
        return 'failing';
    }

    public function resolve(array $map): array
    {
        foreach ($map as $value) {
            if (!$value->nullOnMissing) {
                throw new EntityNotFoundException($value->entityName, $value->value);
            }
        }

        return $map;
    }
}
