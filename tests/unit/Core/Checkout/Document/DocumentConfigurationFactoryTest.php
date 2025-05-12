<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Document;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
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
    public function testCreateConfigurationWithLogo(): void
    {
        $specificConfig = [
            'logo' => [
                'id' => '0196aefd34097365b48db03283350285',
                'fileName' => 'logo',
                'fileExtension' => 'jpg',
            ],
        ];

        $config = DocumentConfigurationFactory::createConfiguration($specificConfig);
        $logo = $config->getLogo();

        static::assertInstanceOf(MediaEntity::class, $logo);
        static::assertEquals('0196aefd34097365b48db03283350285', $logo->getId());
    }
}
