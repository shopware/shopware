<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\SalesChannel\File;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelFile\SalesChannelFileCollection;
use Shopware\Core\System\SalesChannel\File\Discovery\SalesChannelFileDiscovery;
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class SalesChannelFilePublicRequestTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    public function testEnabledSalesChannelFileIsServedThroughNotFoundFallback(): void
    {
        $salesChannelId = $this->getSalesChannelId();
        static::assertNotEmpty($salesChannelId);

        $this->getSalesChannelFileRepository()->upsert([
            [
                'id' => Uuid::randomHex(),
                'salesChannelId' => $salesChannelId,
                'fileFamily' => 'agentic',
                'fileName' => 'llms.txt',
                'enabled' => true,
                'templateOverrides' => [
                    'user_provided_content' => 'Custom public guidance',
                ],
            ],
        ], Context::createDefaultContext());

        $response = $this->request('GET', 'llms.txt', []);
        $content = $response->getContent();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), \is_string($content) ? $content : '');
        static::assertSame('text/plain; charset=utf-8', $response->headers->get('content-type'));
        static::assertIsString($content);
        static::assertStringContainsString('This is a Shopware-powered online shop.', $content);
        static::assertStringContainsString('## Public resources', $content);
        static::assertStringContainsString('Custom public guidance', $content);
    }

    public function testEnabledAiCatalogDoesNotExposeAdminMcpServer(): void
    {
        $salesChannelId = $this->getSalesChannelId();
        static::assertNotEmpty($salesChannelId);

        $this->getSalesChannelFileRepository()->upsert([
            [
                'id' => Uuid::randomHex(),
                'salesChannelId' => $salesChannelId,
                'fileFamily' => 'agentic',
                'fileName' => '.well-known/ai-catalog.json',
                'enabled' => true,
                'templateOverrides' => [],
            ],
        ], Context::createDefaultContext());

        $response = $this->request('GET', '.well-known/ai-catalog.json', []);
        $content = $response->getContent();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), \is_string($content) ? $content : '');
        static::assertSame('application/json; charset=utf-8', $response->headers->get('content-type'));
        static::assertIsString($content);

        $catalog = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($catalog);
        static::assertSame('1.0', $catalog['specVersion']);
        static::assertSame([], $catalog['entries']);
        static::assertStringNotContainsString('/api/_mcp', $content);
    }

    public function testCachedDiscoveryUsesCurrentTwigLoaderForLaterAppTemplates(): void
    {
        $salesChannelId = $this->getSalesChannelId();
        static::assertNotEmpty($salesChannelId);

        static::getContainer()->get('cache.object')->clear();
        static::getContainer()
            ->get(SalesChannelFileDiscovery::class)
            ->get('files/agentic/.well-known/ai-catalog.json.twig');

        $this->createAiCatalogAppTemplate();
        // The test mutates app templates after warming TemplateFinder in the same kernel.
        static::getContainer()->get(TemplateFinder::class)->reset();

        $this->getSalesChannelFileRepository()->upsert([
            [
                'id' => Uuid::randomHex(),
                'salesChannelId' => $salesChannelId,
                'fileFamily' => 'agentic',
                'fileName' => '.well-known/ai-catalog.json',
                'enabled' => true,
                'templateOverrides' => [],
            ],
        ], Context::createDefaultContext());

        $response = $this->request('GET', '.well-known/ai-catalog.json', []);
        $content = $response->getContent();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), \is_string($content) ? $content : '');
        static::assertIsString($content);

        $catalog = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($catalog);
        static::assertSame('urn:air:test:resource:app-template', $catalog['entries'][0]['identifier'] ?? null);
    }

    /**
     * @return EntityRepository<SalesChannelFileCollection>
     */
    private function getSalesChannelFileRepository(): EntityRepository
    {
        return static::getContainer()->get('sales_channel_file.repository');
    }

    private function createAiCatalogAppTemplate(): void
    {
        static::getContainer()->get('app_template.repository')->create([
            [
                'id' => Uuid::randomHex(),
                'path' => 'files/agentic/.well-known/ai-catalog.json.twig',
                'active' => true,
                'template' => <<<'TWIG'
                    {% sw_extends 'files/agentic/.well-known/ai-catalog.json.twig' %}

                    {% block agentic_ai_catalog_entries %}
                        {% set entries = entries|merge([{
                            identifier: 'urn:air:test:resource:app-template',
                            displayName: 'App template entry',
                            type: 'text/plain',
                            url: '/app-template-entry',
                        }]) %}

                        {{ parent() }}
                    {% endblock %}
                    TWIG,
                'app' => [
                    'name' => 'SalesChannelFileCacheApp',
                    'path' => __DIR__ . '/../../../Framework/App/Manifest/_fixtures/test',
                    'version' => '0.0.1',
                    'label' => 'Sales channel file cache app',
                    'accessToken' => 'sales-channel-file-cache-app',
                    'active' => true,
                    'integration' => [
                        'label' => 'Sales channel file cache app',
                        'accessKey' => 'sales-channel-file-cache-app',
                        'secretAccessKey' => 'sales-channel-file-cache-app',
                    ],
                    'aclRole' => [
                        'name' => 'SalesChannelFileCacheApp',
                    ],
                ],
            ],
        ], Context::createDefaultContext());
    }
}
