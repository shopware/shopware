<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\Util;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Util\VariantCombinationLoader;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('inventory')]
class VariantCombinationLoaderTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testLoadsTheCurrentOptionsOfVariantsWhoseIndexingIsQueued(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();
        $repository = static::getContainer()->get('product.repository');

        $repository->create([
            (new ProductBuilder($ids, 'parent'))
                ->price(10)
                ->configuratorSetting('l', 'size')
                ->variant((new ProductBuilder($ids, 'red-s'))->option('red', 'color')->option('s', 'size')->build())
                ->build(),
        ], $context);

        $queuedIndexing = Context::createDefaultContext();
        $queuedIndexing->addState(EntityIndexerRegistry::DISABLE_INDEXING);
        static::getContainer()->get('product_option.repository')->delete([
            ['productId' => $ids->get('red-s'), 'productVersionId' => Defaults::LIVE_VERSION, 'optionId' => $ids->get('s')],
        ], $queuedIndexing);
        $repository->update([['id' => $ids->get('red-s'), 'options' => [['id' => $ids->get('l')]]]], $queuedIndexing);
        $repository->create([
            (new ProductBuilder($ids, 'blue-s'))->parent('parent')->option('blue', 'color')->option('s', 'size')->build(),
            (new ProductBuilder($ids, 'no-options'))->parent('parent')->build(),
        ], $queuedIndexing);

        $optionIds = static::getContainer()->get(Connection::class)->fetchAllKeyValue(
            'SELECT LOWER(HEX(id)), option_ids FROM product WHERE parent_id = :parentId',
            ['parentId' => Uuid::fromHexToBytes($ids->get('parent'))]
        );
        $staleOptionIds = $optionIds[$ids->get('red-s')];
        static::assertIsString($staleOptionIds);
        static::assertStringNotContainsString($ids->get('l'), $staleOptionIds);
        static::assertNull($optionIds[$ids->get('blue-s')]);

        $combinations = static::getContainer()->get(VariantCombinationLoader::class)->load($ids->get('parent'), $context);

        static::assertEqualsCanonicalizing([$ids->get('red-s'), $ids->get('blue-s')], array_keys($combinations));
        static::assertEqualsCanonicalizing([$ids->get('red'), $ids->get('l')], $combinations[$ids->get('red-s')]['options']);
        static::assertEqualsCanonicalizing([$ids->get('blue'), $ids->get('s')], $combinations[$ids->get('blue-s')]['options']);
        static::assertSame('blue-s', $combinations[$ids->get('blue-s')]['productNumber']);
    }
}
