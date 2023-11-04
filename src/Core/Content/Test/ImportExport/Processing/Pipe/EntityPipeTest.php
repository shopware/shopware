<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport\Processing\Pipe;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\PrimaryKeyResolver;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopware\Core\Content\ImportExport\Processing\Pipe\EntityPipe;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationCollection;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('system-settings')]
class EntityPipeTest extends TestCase
{
    use KernelTestBehaviour;

    public function testEntityPipe(): void
    {
        $entityPipe = new EntityPipe(
            $this->getContainer()->get(DefinitionInstanceRegistry::class),
            $this->getContainer()->get(SerializerRegistry::class),
            null,
            null,
            $this->getContainer()->get(PrimaryKeyResolver::class)
        );

        $sourceEntity = ProductDefinition::ENTITY_NAME;
        $config = new Config([], ['sourceEntity' => $sourceEntity], []);
        $id = Uuid::randomHex();

        $product = (new ProductEntity())->assign([
            'id' => $id,
            'stock' => 101,
            'productNumber' => 'P101',
            'active' => true,
            'translations' => new ProductTranslationCollection([
                (new ProductTranslationEntity())->assign([
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'name' => 'test product',
                    '_uniqueIdentifier' => $id . '_' . Defaults::LANGUAGE_SYSTEM,
                ]),
            ]),
        ]);
        $product->setUniqueIdentifier($id);

        $result = iterator_to_array($entityPipe->in($config, $product->jsonSerialize()));

        static::assertSame($product->getId(), $result['id']);
        static::assertSame($product->getTranslations()->first()->getName(), $result['translations']['DEFAULT']['name']);
        static::assertSame((string) $product->getStock(), $result['stock']);
        static::assertSame($product->getProductNumber(), $result['productNumber']);
        static::assertSame('1', $result['active']);

        $result = iterator_to_array($entityPipe->out($config, $result));

        static::assertSame($product->getId(), $result['id']);
        static::assertSame($product->getTranslations()->first()->getName(), $result['translations'][Defaults::LANGUAGE_SYSTEM]['name']);
        static::assertSame($product->getStock(), $result['stock']);
        static::assertSame($product->getProductNumber(), $result['productNumber']);
        static::assertSame($product->getActive(), $result['active']);
    }
}
