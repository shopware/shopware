<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\File\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelFile\SalesChannelFileEntity;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\File\Api\SalesChannelFileController;
use Shopware\Core\System\SalesChannel\File\Discovery\SalesChannelFile;
use Shopware\Core\System\SalesChannel\File\Discovery\SalesChannelFileDiscovery;
use Shopware\Core\System\SalesChannel\File\Loader\SalesChannelFileConfigurationLoader;
use Shopware\Core\System\SalesChannel\File\Loader\SalesChannelFileLoader;
use Shopware\Core\System\SalesChannel\File\Loader\SalesChannelFileSourceLoader;
use Shopware\Core\System\SalesChannel\File\Rendering\SalesChannelFileRenderResult;
use Shopware\Core\System\SalesChannel\File\SalesChannelFileRequestPathResolver;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SalesChannelFileController::class)]
class SalesChannelFileControllerTest extends TestCase
{
    public function testListReturnsDiscoveredFilesWithStoredSalesChannelConfiguration(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelId = Uuid::randomHex();
        $file = new SalesChannelFile(
            'agentic',
            'llms.txt',
            'files/agentic/llms.txt.twig',
            'text/plain; charset=utf-8',
            'files/agentic/llms.txt.twig',
            [
                'Ucp' => '@Ucp/files/agentic/llms.txt.twig',
                'Framework' => '@Framework/files/agentic/llms.txt.twig',
            ],
        );
        $configuration = $this->createConfiguration($salesChannelId, 'agentic', 'llms.txt');
        $sourceLoader = $this->createMock(SalesChannelFileSourceLoader::class);
        $sourceLoader
            ->expects($this->once())
            ->method('load')
            ->with(['Ucp', 'Framework'], $context)
            ->willReturn([
                'Ucp' => [
                    'sourceName' => 'UCP',
                    'sourceType' => 'plugin',
                    'sourceIcon' => 'base64-plugin-icon',
                ],
                'Framework' => [
                    'sourceName' => 'Shopware',
                    'sourceType' => 'shopware',
                    'sourceIcon' => null,
                ],
            ]);

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

        $response = $this->createController($discovery, $configurationLoader, sourceLoader: $sourceLoader)->list('agentic', $salesChannelId, $context);

        static::assertSame(200, $response->getStatusCode());
        static::assertSame([
            'data' => [
                [
                    'fileFamily' => 'agentic',
                    'fileName' => 'llms.txt',
                    'templatePath' => 'files/agentic/llms.txt.twig',
                    'contentType' => 'text/plain; charset=utf-8',
                    'templates' => [
                        [
                            'twigNamespace' => 'Ucp',
                            'templateName' => '@Ucp/files/agentic/llms.txt.twig',
                            'templateContent' => '{% block user_provided_content %}{% endblock %}',
                            'sourceName' => 'UCP',
                            'sourceType' => 'plugin',
                            'sourceIcon' => 'base64-plugin-icon',
                            'role' => 'extension',
                        ],
                        [
                            'twigNamespace' => 'Framework',
                            'templateName' => '@Framework/files/agentic/llms.txt.twig',
                            'templateContent' => 'Core template',
                            'sourceName' => 'Shopware',
                            'sourceType' => 'shopware',
                            'sourceIcon' => null,
                            'role' => 'base',
                        ],
                    ],
                    'supportsUserProvidedContent' => true,
                    'configuration' => [
                        'id' => $configuration->getId(),
                        'enabled' => true,
                        'templateOverrides' => [
                            'Framework' => 'Merchant override',
                        ],
                    ],
                ],
            ],
        ], $this->decodeResponse($response->getContent()));
    }

    public function testPreviewRendersUnsavedTemplateOverridesForSalesChannel(): void
    {
        $salesChannelId = Uuid::randomHex();
        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        $contextFactory = $this->createMock(AbstractSalesChannelContextFactory::class);
        $contextFactory
            ->expects($this->once())
            ->method('create')
            ->with(
                static::callback(static fn (string $token): bool => Uuid::isValid($token)),
                $salesChannelId,
            )
            ->willReturn($salesChannelContext);

        $loader = $this->createMock(SalesChannelFileLoader::class);
        $loader
            ->expects($this->once())
            ->method('preview')
            ->with(
                'files/agentic/llms.txt.twig',
                $salesChannelContext,
                ['Framework' => 'Unsaved override']
            )
            ->willReturn(new SalesChannelFileRenderResult('llms.txt', 'Rendered preview', 'text/plain; charset=utf-8'));

        $controller = $this->createController(
            salesChannelFileLoader: $loader,
            salesChannelContextFactory: $contextFactory,
        );

        $response = $controller->preview('agentic', $salesChannelId, new RequestDataBag([
            'fileName' => 'llms.txt',
            'templateOverrides' => new RequestDataBag([
                'Framework' => 'Unsaved override',
            ]),
        ]));

        static::assertSame(200, $response->getStatusCode());
        static::assertSame([
            'fileName' => 'llms.txt',
            'contentType' => 'text/plain; charset=utf-8',
            'content' => 'Rendered preview',
        ], $this->decodeResponse($response->getContent()));
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

    private function createController(
        ?SalesChannelFileDiscovery $discovery = null,
        ?SalesChannelFileConfigurationLoader $configurationLoader = null,
        ?SalesChannelFileLoader $salesChannelFileLoader = null,
        ?AbstractSalesChannelContextFactory $salesChannelContextFactory = null,
        ?Environment $twig = null,
        ?SalesChannelFileSourceLoader $sourceLoader = null,
    ): SalesChannelFileController {
        return new SalesChannelFileController(
            $discovery ?? $this->createMock(SalesChannelFileDiscovery::class),
            $configurationLoader ?? $this->createMock(SalesChannelFileConfigurationLoader::class),
            $salesChannelFileLoader ?? $this->createMock(SalesChannelFileLoader::class),
            $salesChannelContextFactory ?? $this->createMock(AbstractSalesChannelContextFactory::class),
            new SalesChannelFileRequestPathResolver(),
            $twig ?? new Environment(new ArrayLoader([
                '@Ucp/files/agentic/llms.txt.twig' => '{% block user_provided_content %}{% endblock %}',
                '@Framework/files/agentic/llms.txt.twig' => 'Core template',
            ])),
            $sourceLoader ?? $this->createMock(SalesChannelFileSourceLoader::class),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(string|false $content): array
    {
        static::assertIsString($content);

        $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($data);

        return $data;
    }
}
