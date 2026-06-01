<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\File\Loader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelFile\SalesChannelFileCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelFile\SalesChannelFileEntity;
use Shopware\Core\System\SalesChannel\File\Loader\SalesChannelFileConfigurationLoader;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SalesChannelFileConfigurationLoader::class)]
class SalesChannelFileConfigurationLoaderTest extends TestCase
{
    public function testLoadReturnsConfiguredFileForSalesChannelFamilyAndFileName(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelId = Uuid::randomHex();
        $configuration = $this->createConfiguration($salesChannelId, 'agentic', 'llms.txt');

        /** @var StaticEntityRepository<SalesChannelFileCollection> $repository */
        $repository = new StaticEntityRepository([
            function (Criteria $criteria, Context $searchContext) use ($context, $salesChannelId, $configuration): SalesChannelFileCollection {
                static::assertSame($context, $searchContext);
                static::assertSame(1, $criteria->getLimit());
                $this->assertEqualsFilters($criteria, [
                    'salesChannelId' => $salesChannelId,
                    'fileFamily' => 'agentic',
                    'fileName' => 'llms.txt',
                ]);

                return new SalesChannelFileCollection([$configuration]);
            },
        ]);

        $result = (new SalesChannelFileConfigurationLoader($repository))->load('agentic', 'llms.txt', $salesChannelId, $context);

        static::assertSame($configuration, $result);
    }

    public function testLoadForFileFamilyReturnsConfigurationsKeyedByFileName(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelId = Uuid::randomHex();
        $llms = $this->createConfiguration($salesChannelId, 'agentic', 'llms.txt');
        $agents = $this->createConfiguration($salesChannelId, 'agentic', 'agents.md');

        /** @var StaticEntityRepository<SalesChannelFileCollection> $repository */
        $repository = new StaticEntityRepository([
            function (Criteria $criteria, Context $searchContext) use ($context, $salesChannelId, $llms, $agents): SalesChannelFileCollection {
                static::assertSame($context, $searchContext);
                static::assertNull($criteria->getLimit());
                $this->assertEqualsFilters($criteria, [
                    'salesChannelId' => $salesChannelId,
                    'fileFamily' => 'agentic',
                ]);

                return new SalesChannelFileCollection([$llms, $agents]);
            },
        ]);

        $result = (new SalesChannelFileConfigurationLoader($repository))->loadForFileFamily('agentic', $salesChannelId, $context);

        static::assertSame([
            'llms.txt' => $llms,
            'agents.md' => $agents,
        ], $result);
    }

    /**
     * @param array<string, string> $expected
     */
    private function assertEqualsFilters(Criteria $criteria, array $expected): void
    {
        $filters = [];

        foreach ($criteria->getFilters() as $filter) {
            static::assertInstanceOf(EqualsFilter::class, $filter);
            $filters[$filter->getField()] = $filter->getValue();
        }

        static::assertSame($expected, $filters);
    }

    private function createConfiguration(string $salesChannelId, string $fileFamily, string $fileName): SalesChannelFileEntity
    {
        $configuration = new SalesChannelFileEntity();
        $configuration->setId(Uuid::randomHex());
        $configuration->setSalesChannelId($salesChannelId);
        $configuration->setFileFamily($fileFamily);
        $configuration->setFileName($fileName);

        return $configuration;
    }
}
