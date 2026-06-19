<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\SalesChannel\File;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelFile\SalesChannelFileCollection;
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

    /**
     * @return EntityRepository<SalesChannelFileCollection>
     */
    private function getSalesChannelFileRepository(): EntityRepository
    {
        return static::getContainer()->get('sales_channel_file.repository');
    }
}
