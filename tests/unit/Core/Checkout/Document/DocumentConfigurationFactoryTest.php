<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentConfigurationFactory;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Log\Package;

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
            'logo' => [
                'id' => '0196aefd34097365b48db03283350285',
                'fileName' => 'logo',
            ],
        ];

        $mergedConfig = DocumentConfigurationFactory::mergeConfiguration($baseConfig, $additionalConfig);
        $logo = $mergedConfig->getLogo();

        static::assertInstanceOf(MediaEntity::class, $logo);
        static::assertSame('0196aefd34097365b48db03283350285', $logo->getId());
        static::assertSame('logo', $logo->getFileName());
    }

    public function testMergeConfigurationWithEntityObjectAndUseSetterMethod(): void
    {
        $baseConfig = new DocumentConfiguration();

        $logo = new MediaEntity();
        $logo->setId('0196aefd34097365b48db03283350285');
        $logo->setFileName('logo');

        $additionalConfig = [
            'logo' => $logo,
        ];

        $mergedConfig = DocumentConfigurationFactory::mergeConfiguration($baseConfig, $additionalConfig);
        $logo = $mergedConfig->getLogo();

        static::assertInstanceOf(MediaEntity::class, $logo);
        static::assertSame('0196aefd34097365b48db03283350285', $logo->getId());
        static::assertSame('logo', $logo->getFileName());
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
}
