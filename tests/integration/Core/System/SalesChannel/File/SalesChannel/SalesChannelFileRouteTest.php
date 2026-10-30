<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\SalesChannel\File\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class SalesChannelFileRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('headless-sales-channel'),
            'typeId' => Defaults::SALES_CHANNEL_TYPE_API,
            'domains' => [['url' => 'https://headless-frontend.test']],
        ]);
    }

    public function testEnabledFileIsServedToHeadlessSalesChannel(): void
    {
        $this->enableFile('llms.txt', ['user_provided_content' => 'Custom public guidance']);

        $this->browser->request('GET', '/store-api/sales-channel-file/llms.txt');
        $response = $this->browser->getResponse();
        $content = (string) $response->getContent();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);
        static::assertSame('text/plain; charset=utf-8', $response->headers->get('content-type'));
        static::assertStringContainsString('This is a Shopware-powered online shop.', $content);
        static::assertStringContainsString('Custom public guidance', $content);
        static::assertStringContainsString('](' . $this->getAppUrl() . '/store-api/context)', $content);
    }

    public function testAiCatalogInSubFolderListsTheStoreApiMcpServerOfTheHeadlessSalesChannel(): void
    {
        $this->enableFile('.well-known/ai-catalog.json');

        $this->browser->request('GET', '/store-api/sales-channel-file/.well-known/ai-catalog.json');
        $response = $this->browser->getResponse();
        $content = (string) $response->getContent();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);
        static::assertSame('application/json; charset=utf-8', $response->headers->get('content-type'));

        $catalog = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($catalog);
        static::assertSame('urn:air:headless-frontend.test:server:store-api-mcp', $catalog['entries'][0]['identifier'] ?? null);
        static::assertSame($this->getAppUrl() . '/store-api/_mcp', $catalog['entries'][0]['url'] ?? null);
    }

    public function testDisabledFileIsNotFound(): void
    {
        $this->browser->request('GET', '/store-api/sales-channel-file/AGENTS.md');

        static::assertSame(Response::HTTP_NOT_FOUND, $this->browser->getResponse()->getStatusCode());
    }

    private function getAppUrl(): string
    {
        return rtrim((string) EnvironmentHelper::getVariable('APP_URL'), '/');
    }

    /**
     * @param array<string, string> $templateOverrides
     */
    private function enableFile(string $fileName, array $templateOverrides = []): void
    {
        static::getContainer()->get('sales_channel_file.repository')->upsert([
            [
                'id' => Uuid::randomHex(),
                'salesChannelId' => $this->ids->get('headless-sales-channel'),
                'fileFamily' => 'agentic',
                'fileName' => $fileName,
                'enabled' => true,
                'templateOverrides' => $templateOverrides,
            ],
        ], Context::createDefaultContext());
    }
}
