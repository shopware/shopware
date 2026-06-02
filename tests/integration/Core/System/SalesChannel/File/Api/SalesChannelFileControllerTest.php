<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\SalesChannel\File\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelFile\SalesChannelFileCollection;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class SalesChannelFileControllerTest extends TestCase
{
    use AdminApiTestBehaviour;
    use IntegrationTestBehaviour;

    public function testItListsDiscoveredFilesWithSalesChannelConfiguration(): void
    {
        $this->getSalesChannelFileRepository()->upsert([
            [
                'id' => Uuid::randomHex(),
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'fileFamily' => 'agentic',
                'fileName' => 'llms.txt',
                'enabled' => true,
                'templateOverrides' => [
                    'Framework' => 'merchant override',
                ],
            ],
        ], Context::createDefaultContext());

        $this->getBrowser()->request('GET', '/api/_action/sales-channel-file/agentic/' . TestDefaults::SALES_CHANNEL);

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());

        $response = $this->decodeResponse();
        $files = array_column($response['data'], null, 'fileName');

        static::assertSame(['agents.md', 'llms.txt'], array_keys($files));
        static::assertSame('agentic', $files['llms.txt']['fileFamily']);
        static::assertSame('text/plain; charset=utf-8', $files['llms.txt']['contentType']);
        static::assertArrayNotHasKey('templatePath', $files['llms.txt']);
        static::assertArrayNotHasKey('templates', $files['llms.txt']);
        static::assertArrayNotHasKey('supportsUserProvidedContent', $files['llms.txt']);
        static::assertIsString($files['llms.txt']['configuration']['id']);
        static::assertTrue($files['llms.txt']['configuration']['enabled']);
        static::assertSame(['Framework' => 'merchant override'], $files['llms.txt']['configuration']['templateOverrides']);
    }

    public function testItLoadsDiscoveredFileDetailWithTemplateContent(): void
    {
        $this->getSalesChannelFileRepository()->upsert([
            [
                'id' => Uuid::randomHex(),
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'fileFamily' => 'agentic',
                'fileName' => 'llms.txt',
                'enabled' => true,
                'templateOverrides' => [
                    'Framework' => 'merchant override',
                ],
            ],
        ], Context::createDefaultContext());

        $this->getBrowser()->request('GET', '/api/_action/sales-channel-file/agentic/' . TestDefaults::SALES_CHANNEL . '/detail?fileName=llms.txt');

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());

        $response = $this->decodeResponse();
        $file = $response['data'];

        static::assertSame('agentic', $file['fileFamily']);
        static::assertSame('llms.txt', $file['fileName']);
        static::assertSame('files/agentic/llms.txt.twig', $file['templatePath']);
        static::assertSame('text/plain; charset=utf-8', $file['contentType']);
        static::assertTrue($file['supportsUserProvidedContent']);
        static::assertSame('Framework', $file['templates'][0]['twigNamespace']);
        static::assertSame('@Framework/files/agentic/llms.txt.twig', $file['templates'][0]['templateName']);
        static::assertSame('base', $file['templates'][0]['role']);
        static::assertIsString($file['templates'][0]['templateContent']);
        static::assertStringContainsString('agentic_llms_txt', $file['templates'][0]['templateContent']);
        static::assertIsString($file['configuration']['id']);
        static::assertTrue($file['configuration']['enabled']);
        static::assertSame(['Framework' => 'merchant override'], $file['configuration']['templateOverrides']);
    }

    public function testItPreviewsSalesChannelFileWithMerchantOverrides(): void
    {
        $this->getBrowser()->jsonRequest('POST', '/api/_action/sales-channel-file/agentic/' . TestDefaults::SALES_CHANNEL . '/preview', [
            'fileName' => 'llms.txt',
            'templateOverrides' => [
                'Framework' => 'Preview body',
            ],
        ]);

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());

        $response = $this->decodeResponse();

        static::assertSame('llms.txt', $response['fileName']);
        static::assertSame('text/plain; charset=utf-8', $response['contentType']);
        static::assertSame('Preview body', $response['content']);
    }

    public function testItRejectsInvalidPreviewPath(): void
    {
        $this->getBrowser()->jsonRequest('POST', '/api/_action/sales-channel-file/agentic/' . TestDefaults::SALES_CHANNEL . '/preview', [
            'fileName' => '../llms.txt',
            'templateOverrides' => [],
        ]);

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->getBrowser()->getResponse()->getStatusCode());

        $response = $this->decodeResponse();

        static::assertSame('FRAMEWORK__SALES_CHANNEL_FILE_INVALID_PATH', $response['errors'][0]['code']);
    }

    /**
     * @return EntityRepository<SalesChannelFileCollection>
     */
    private function getSalesChannelFileRepository(): EntityRepository
    {
        return static::getContainer()->get('sales_channel_file.repository');
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(): array
    {
        $content = $this->getBrowser()->getResponse()->getContent();
        static::assertIsString($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($response);

        return $response;
    }
}
