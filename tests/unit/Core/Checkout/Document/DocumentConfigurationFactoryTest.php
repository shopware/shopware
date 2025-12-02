<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentConfigurationFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryEntity;

/**
 * @internal
 */
#[CoversClass(DocumentConfigurationFactory::class)]
#[Package('after-sales')]
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
