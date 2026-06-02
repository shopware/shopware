<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\File\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelFile\SalesChannelFileEntity;
use Shopware\Core\System\SalesChannel\File\Api\SalesChannelFileAdministrationConfiguration;
use Shopware\Core\System\SalesChannel\File\Api\SalesChannelFileAdministrationDetail;
use Shopware\Core\System\SalesChannel\File\Api\SalesChannelFileAdministrationListItem;
use Shopware\Core\System\SalesChannel\File\Api\SalesChannelFileAdministrationReader;
use Shopware\Core\System\SalesChannel\File\Api\SalesChannelFileAdministrationTemplate;
use Shopware\Core\System\SalesChannel\File\Discovery\SalesChannelFile;
use Shopware\Core\System\SalesChannel\File\Discovery\SalesChannelFileDiscovery;
use Shopware\Core\System\SalesChannel\File\Loader\SalesChannelFileConfigurationLoader;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SalesChannelFileAdministrationReader::class)]
class SalesChannelFileAdministrationReaderTest extends TestCase
{
    public function testListReturnsLightweightFileDescriptorsWithStoredConfiguration(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelId = Uuid::randomHex();
        $file = $this->createSalesChannelFile();
        $configuration = $this->createConfiguration($salesChannelId, 'agentic', 'llms.txt');

        $discovery = $this->createMock(SalesChannelFileDiscovery::class);
        $discovery
            ->expects($this->once())
            ->method('discover')
            ->with('agentic')
            ->willReturn(['llms.txt' => $file]);

        $configurationLoader = $this->createMock(SalesChannelFileConfigurationLoader::class);
        $configurationLoader
            ->expects($this->once())
            ->method('loadForFileFamily')
            ->with('agentic', $salesChannelId, $context)
            ->willReturn(['llms.txt' => $configuration]);

        $reader = new SalesChannelFileAdministrationReader(
            $discovery,
            $configurationLoader,
            $this->createTwigEnvironment(),
        );

        static::assertEquals([
            new SalesChannelFileAdministrationListItem(
                'agentic',
                'llms.txt',
                'text/plain; charset=utf-8',
                new SalesChannelFileAdministrationConfiguration(
                    $configuration->getId(),
                    true,
                    [
                        'Framework' => 'Merchant override',
                    ],
                ),
            ),
        ], $reader->list('agentic', $salesChannelId, $context));
    }

    public function testDetailReturnsTemplateSourcesAndContent(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelId = Uuid::randomHex();
        $file = $this->createSalesChannelFile();
        $configuration = $this->createConfiguration($salesChannelId, 'agentic', 'llms.txt');

        $discovery = $this->createMock(SalesChannelFileDiscovery::class);
        $discovery
            ->expects($this->once())
            ->method('discover')
            ->with('agentic')
            ->willReturn(['llms.txt' => $file]);

        $configurationLoader = $this->createMock(SalesChannelFileConfigurationLoader::class);
        $configurationLoader
            ->expects($this->once())
            ->method('load')
            ->with('agentic', 'llms.txt', $salesChannelId, $context)
            ->willReturn($configuration);

        $reader = new SalesChannelFileAdministrationReader(
            $discovery,
            $configurationLoader,
            $this->createTwigEnvironment(),
        );

        static::assertEquals(new SalesChannelFileAdministrationDetail(
            'agentic',
            'llms.txt',
            'files/agentic/llms.txt.twig',
            'text/plain; charset=utf-8',
            [
                new SalesChannelFileAdministrationTemplate(
                    'Framework',
                    '@Framework/files/agentic/llms.txt.twig',
                    'Core template',
                    'base',
                ),
                new SalesChannelFileAdministrationTemplate(
                    'Ucp',
                    '@Ucp/files/agentic/llms.txt.twig',
                    '{% block user_provided_content %}{% endblock %}',
                    'extension',
                ),
            ],
            true,
            new SalesChannelFileAdministrationConfiguration(
                $configuration->getId(),
                true,
                [
                    'Framework' => 'Merchant override',
                ],
            ),
        ), $reader->detail('agentic', 'llms.txt', $salesChannelId, $context));
    }

    public function testDetailReturnsNullForUnknownFile(): void
    {
        $context = Context::createDefaultContext();

        $discovery = $this->createMock(SalesChannelFileDiscovery::class);
        $discovery
            ->expects($this->once())
            ->method('discover')
            ->with('agentic')
            ->willReturn([]);

        $configurationLoader = $this->createMock(SalesChannelFileConfigurationLoader::class);
        $configurationLoader
            ->expects($this->never())
            ->method('load');

        $reader = new SalesChannelFileAdministrationReader(
            $discovery,
            $configurationLoader,
            $this->createTwigEnvironment(),
        );

        static::assertNull($reader->detail('agentic', 'missing.txt', Uuid::randomHex(), $context));
    }

    private function createSalesChannelFile(): SalesChannelFile
    {
        return new SalesChannelFile(
            'agentic',
            'llms.txt',
            'files/agentic/llms.txt.twig',
            'text/plain; charset=utf-8',
            'files/agentic/llms.txt.twig',
            [
                'Framework' => '@Framework/files/agentic/llms.txt.twig',
                'Ucp' => '@Ucp/files/agentic/llms.txt.twig',
            ],
        );
    }

    private function createConfiguration(string $salesChannelId, string $fileFamily, string $fileName): SalesChannelFileEntity
    {
        $configuration = new SalesChannelFileEntity();
        $configuration->setId(Uuid::randomHex());
        $configuration->setSalesChannelId($salesChannelId);
        $configuration->setFileFamily($fileFamily);
        $configuration->setFileName($fileName);
        $configuration->setEnabled(true);
        $configuration->setTemplateOverrides(['Framework' => 'Merchant override']);

        return $configuration;
    }

    private function createTwigEnvironment(): Environment
    {
        return new Environment(new ArrayLoader([
            '@Ucp/files/agentic/llms.txt.twig' => '{% block user_provided_content %}{% endblock %}',
            '@Framework/files/agentic/llms.txt.twig' => 'Core template',
        ]));
    }
}
