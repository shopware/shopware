<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Cache;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\CachedEntityReader;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityReader;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Core\System\Tax\TaxDefinition;
use Shopware\Core\System\Tax\TaxEntity;

class CachedEntityReaderTest extends TestCase
{
    use KernelTestBehaviour;

    public function testCacheHit()
    {
        $dbalReader = $this->createMock(EntityReader::class);

        $id1 = Uuid::uuid4()->getHex();
        $id2 = Uuid::uuid4()->getHex();

        $dbalReader->expects(static::once())
            ->method('read')
            ->will(
                $this->returnValue(
                    new TaxCollection([
                        (new TaxEntity())->assign([
                            'id' => $id1,
                            '_uniqueIdentifier' => $id1,
                            'taxRate' => 15,
                            'name' => 'test',
                            'products' => new ProductCollection([
                                (new ProductEntity())->assign([
                                    'id' => $id1,
                                    '_uniqueIdentifier' => $id1,
                                    'tax' => (new TaxEntity())->assign([
                                        'id' => $id1,
                                        '_uniqueIdentifier' => $id1,
                                    ]),
                                ]),
                            ]),
                        ]),
                        (new TaxEntity())->assign([
                            'id' => $id2,
                            '_uniqueIdentifier' => $id2,
                            'taxRate' => 12,
                            'name' => 'test2',
                        ]),
                    ])
                )
            );

        $cache = $this->getContainer()->get('shopware.cache');

        $generator = $this->getContainer()->get(EntityCacheKeyGenerator::class);

        $cachedReader = new CachedEntityReader($cache, $dbalReader, $generator);

        $criteria = new Criteria([$id1, $id2]);

        $context = Context::createDefaultContext();

        //first call should not match and the expects of the dbal reader should called
        $databaseEntities = $cachedReader->read(TaxDefinition::class, $criteria, $context);

        //second call should hit the cache items and the dbal reader shouldn't be called
        $cachedEntities = $cachedReader->read(TaxDefinition::class, $criteria, $context);

        static::assertEquals($databaseEntities, $cachedEntities);
    }
}
