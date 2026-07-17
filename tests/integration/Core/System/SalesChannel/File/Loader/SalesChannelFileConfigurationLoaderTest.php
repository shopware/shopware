<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\SalesChannel\File\Loader;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelFile\SalesChannelFileCollection;
use Shopware\Core\System\SalesChannel\File\Loader\SalesChannelFileConfigurationLoader;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('framework')]
class SalesChannelFileConfigurationLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testItLoadsConfigurationForFile(): void
    {
        $id = Uuid::randomHex();
        $fileName = 'custom-' . Uuid::randomHex() . '.txt';

        $this->getSalesChannelFileRepository()->create([
            [
                'id' => $id,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'fileFamily' => 'agentic',
                'fileName' => $fileName,
                'enabled' => true,
                'templateOverrides' => [
                    'Framework' => 'merchant override',
                ],
            ],
        ], Context::createDefaultContext());

        $configuration = $this->getConfigurationLoader()->load(
            'agentic',
            $fileName,
            TestDefaults::SALES_CHANNEL,
            Context::createDefaultContext()
        );

        static::assertNotNull($configuration);
        static::assertSame($id, $configuration->getId());
        static::assertSame(TestDefaults::SALES_CHANNEL, $configuration->getSalesChannelId());
        static::assertSame('agentic', $configuration->getFileFamily());
        static::assertSame($fileName, $configuration->getFileName());
        static::assertTrue($configuration->isEnabled());
        static::assertSame(['Framework' => 'merchant override'], $configuration->getTemplateOverrides());
    }

    public function testItLoadsConfigurationsForFileFamilyIndexedByFileName(): void
    {
        $firstId = Uuid::randomHex();
        $secondId = Uuid::randomHex();
        $otherFamilyId = Uuid::randomHex();
        $suffix = Uuid::randomHex();
        $firstFileName = 'custom-' . $suffix . '-first.txt';
        $secondFileName = 'custom-' . $suffix . '-second.txt';
        $otherFamilyFileName = 'robots-' . $suffix . '.txt';

        $this->getSalesChannelFileRepository()->create([
            [
                'id' => $firstId,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'fileFamily' => 'agentic',
                'fileName' => $firstFileName,
                'enabled' => true,
                'templateOverrides' => [],
            ],
            [
                'id' => $secondId,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'fileFamily' => 'agentic',
                'fileName' => $secondFileName,
                'enabled' => false,
                'templateOverrides' => [
                    'Ucp' => 'plugin override',
                ],
            ],
            [
                'id' => $otherFamilyId,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'fileFamily' => 'seo',
                'fileName' => $otherFamilyFileName,
                'enabled' => true,
                'templateOverrides' => [],
            ],
        ], Context::createDefaultContext());

        $configurations = $this->getConfigurationLoader()->loadForFileFamily(
            'agentic',
            TestDefaults::SALES_CHANNEL,
            Context::createDefaultContext()
        );

        static::assertArrayHasKey($firstFileName, $configurations);
        static::assertArrayHasKey($secondFileName, $configurations);
        static::assertSame($firstId, $configurations[$firstFileName]->getId());
        static::assertSame($secondId, $configurations[$secondFileName]->getId());
        static::assertFalse($configurations[$secondFileName]->isEnabled());
        static::assertArrayNotHasKey($otherFamilyFileName, $configurations);

        foreach ($configurations as $configuration) {
            static::assertSame('agentic', $configuration->getFileFamily());
        }
    }

    /**
     * @return EntityRepository<SalesChannelFileCollection>
     */
    private function getSalesChannelFileRepository(): EntityRepository
    {
        return static::getContainer()->get('sales_channel_file.repository');
    }

    private function getConfigurationLoader(): SalesChannelFileConfigurationLoader
    {
        return static::getContainer()->get(SalesChannelFileConfigurationLoader::class);
    }
}
