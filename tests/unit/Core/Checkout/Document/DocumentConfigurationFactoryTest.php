<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentConfigurationFactory;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\Country\CountryEntity;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentConfigurationFactory::class)]
class DocumentConfigurationFactoryTest extends TestCase
{
    public function testMergeConfigurationConvertsArrayToEntityObjectAndUseSetterMethod(): void
    {
        $baseConfig = new DocumentConfiguration();
        $additionalConfig = [
            'companyCountry' => [
                'id' => '0196aefd34097365b48db03283350285',
                'name' => 'Germany',
            ],
        ];

        $mergedConfig = DocumentConfigurationFactory::mergeConfiguration($baseConfig, $additionalConfig);
        $companyCountry = $mergedConfig->getCompanyCountry();

        static::assertInstanceOf(CountryEntity::class, $companyCountry);
        static::assertSame('0196aefd34097365b48db03283350285', $companyCountry->getId());
        static::assertSame('Germany', $companyCountry->getName());
    }

    public function testMergeConfigurationRestoresEntitiesOfAStoredDocumentConfig(): void
    {
        $logo = new MediaEntity();
        $logo->setId('0196aefd34097365b48db03283350286');
        $logo->setUrl('https://shop.example/media/logo.png');
        $logo->addExtension('foreignKeys', new ArrayEntity(['mediaFolderId' => null]));
        $logo->addArrayExtension('myPlugin', ['field' => 'value']);

        $companyCountry = new CountryEntity();
        $companyCountry->setId('0196aefd34097365b48db03283350285');
        $companyCountry->addExtension('foreignKeys', new ArrayEntity());

        // A document stores its config as JSON, so the entities come back as arrays.
        $storedJson = json_encode(['logo' => $logo, 'companyCountry' => $companyCountry], \JSON_THROW_ON_ERROR);
        $storedConfig = json_decode($storedJson, true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($storedConfig);

        $mergedConfig = DocumentConfigurationFactory::mergeConfiguration(new DocumentConfiguration(), $storedConfig);

        $encodedConfig = json_decode(json_encode($mergedConfig, \JSON_THROW_ON_ERROR), true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($encodedConfig);
        static::assertSame('https://shop.example/media/logo.png', $encodedConfig['logo']['url'] ?? null);

        $mergedLogo = $mergedConfig->getLogo();
        static::assertInstanceOf(MediaEntity::class, $mergedLogo);
        static::assertSame('https://shop.example/media/logo.png', $mergedLogo->getUrl());
        $pluginData = $mergedLogo->getExtension('myPlugin');
        static::assertInstanceOf(ArrayStruct::class, $pluginData);
        static::assertSame('value', $pluginData->get('field'));
        static::assertSame('value', $encodedConfig['logo']['extensions']['myPlugin']['field'] ?? null);

        $mergedCompanyCountry = $mergedConfig->getCompanyCountry();
        static::assertInstanceOf(CountryEntity::class, $mergedCompanyCountry);
        static::assertSame('0196aefd34097365b48db03283350285', $mergedCompanyCountry->getId());
        static::assertInstanceOf(ArrayStruct::class, $mergedCompanyCountry->getExtension('foreignKeys'));
    }

    public function testMergeConfigurationKeepsStructExtensionsOfAnEntityArray(): void
    {
        $extension = new ArrayStruct(['key' => 'value']);
        $additionalConfig = [
            'logo' => [
                'id' => '0196aefd34097365b48db03283350286',
                'extensions' => ['pluginData' => $extension, 'foreignKeys' => ['mediaFolderId' => null]],
            ],
        ];

        $mergedConfig = DocumentConfigurationFactory::mergeConfiguration(new DocumentConfiguration(), $additionalConfig);

        $mergedLogo = $mergedConfig->getLogo();
        static::assertInstanceOf(MediaEntity::class, $mergedLogo);
        static::assertSame($extension, $mergedLogo->getExtension('pluginData'));
        static::assertEquals(new ArrayStruct(['mediaFolderId' => null]), $mergedLogo->getExtension('foreignKeys'));
    }

    public function testMergeConfigurationWithEntityObjectAndUseSetterMethod(): void
    {
        $baseConfig = new DocumentConfiguration();

        $companyCountry = new CountryEntity();
        $companyCountry->setId('0196aefd34097365b48db03283350285');
        $companyCountry->setName('Germany');

        $additionalConfig = [
            'companyCountry' => $companyCountry,
        ];

        $mergedConfig = DocumentConfigurationFactory::mergeConfiguration($baseConfig, $additionalConfig);
        $companyCountry = $mergedConfig->getCompanyCountry();

        static::assertInstanceOf(CountryEntity::class, $companyCountry);
        static::assertSame('0196aefd34097365b48db03283350285', $companyCountry->getId());
        static::assertSame('Germany', $companyCountry->getName());
    }

    public function testMergeConfigurationWithDynamicProperties(): void
    {
        $baseConfig = new DocumentConfiguration();
        $additionalConfig = [
            'nonExistentProperty' => 'someValue',
            'pluginSpecificField' => true,
        ];

        $mergedConfig = DocumentConfigurationFactory::mergeConfiguration($baseConfig, $additionalConfig);

        static::assertSame('someValue', $mergedConfig->__get('nonExistentProperty'));
        static::assertTrue($mergedConfig->__get('pluginSpecificField'));
    }

    public function testMergeConfigurationWithPrimitiveTypesAndArrays(): void
    {
        $baseConfig = new DocumentConfiguration();
        $additionalConfig = [
            'documentNumber' => '12345',
            'itemsPerPage' => 10,
            'fileTypes' => ['pdf', 'html', 'xml'],
        ];

        $mergedConfig = DocumentConfigurationFactory::mergeConfiguration($baseConfig, $additionalConfig);

        static::assertSame('12345', $mergedConfig->getDocumentNumber());
        static::assertSame(10, $mergedConfig->__get('itemsPerPage'));
        static::assertSame(['pdf', 'html', 'xml'], $mergedConfig->getFileTypes());
    }

    public function testMergeConfigurationWithCustomArray(): void
    {
        $baseConfig = new DocumentConfiguration();

        $additionalConfig = [
            'companyName' => 'Example Company',
            'custom' => [
                'invoiceNumber' => '1',
            ],
        ];

        $mergedConfig = DocumentConfigurationFactory::mergeConfiguration($baseConfig, $additionalConfig);

        static::assertSame('Example Company', $mergedConfig->getCompanyName());
        $customData = $mergedConfig->__get('custom');
        static::assertIsArray($customData);
        static::assertSame('1', $customData['invoiceNumber']);
    }

    public function testMergeConfigurationStringToIntConversion(): void
    {
        $baseConfig = new DocumentConfiguration();
        $additionalConfig = [
            'itemsPerPage' => '10',
        ];

        $mergedConfig = DocumentConfigurationFactory::mergeConfiguration($baseConfig, $additionalConfig);

        static::assertSame(10, $mergedConfig->__get('itemsPerPage'));
    }
}
