<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\HealthCheck;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Renderer\DeliveryNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdEmbeddedRenderer;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Service\HtmlRenderer;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Framework\SystemCheck\DocumentRenderReadinessCheck;
use Shopware\Tests\Integration\Core\Checkout\Document\DocumentTrait;
use Shopware\Core\Checkout\Document\Renderer\ZugferdRenderer;

/**
 * @internal
 */
#[CoversClass(DocumentRenderReadinessCheck::class)]
#[Package('after-sales')]
class DocumentRenderReadinessCheckTest extends TestCase
{
    use CacheTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use DocumentTrait;


    private Connection $connection;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    private SalesChannelContext $salesChannelContext;

    private DocumentGenerator $documentGenerator;


    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = static::getContainer()->get(Connection::class);
        $this->productRepository = static::getContainer()->get('product.repository');
        $this->documentGenerator = static::getContainer()->get(DocumentGenerator::class);
        $this->context = Context::createDefaultContext();

        $this->salesChannelContext = static::getContainer()
            ->get(SalesChannelContextFactory::class)
            ->create(
                Uuid::randomHex(),
                TestDefaults::SALES_CHANNEL,
                [
                    SalesChannelContextService::CUSTOMER_ID => $this->createCustomer(),
                ]
            );

        $priceRuleId = Uuid::randomHex();
        $this->salesChannelContext->setRuleIds([$priceRuleId]);
    }

    public function testSkipWhenNoOrdersExist(): void
    {
        $this->connection->executeStatement('DELETE FROM `document`');
        $this->connection->executeStatement('DELETE FROM `order`');

        $healthCheck = $this->createCheck();
        $result = $healthCheck->run();

        static::assertTrue($result->healthy);
        static::assertSame(Status::SKIPPED, $result->status);
        static::assertSame('No orders found; document previews are skipped.', $result->message);
    }

    public function testAllDocumentsRenderedSuccessfully()
    {
        $cart = $this->generateDemoCart(1);
        $orderId = $this->persistCart($cart);

        $this->generateDocument(InvoiceRenderer::TYPE, $orderId);
        $this->generateDocument(DeliveryNoteRenderer::TYPE, $orderId);
        $this->generateDocument(ZugferdRenderer::TYPE, $orderId);
        $this->generateDocument(ZugferdEmbeddedRenderer::TYPE, $orderId);

        $healthCheck = $this->createCheck();
        $healtCheckResult = $healthCheck->run();

        static::assertTrue($healtCheckResult->healthy);
        static::assertSame(Status::OK, $healtCheckResult->status);
        static::assertSame('All documents rendered successfully.', $healtCheckResult->message);

    }

    private function createCheck(): DocumentRenderReadinessCheck
    {
        return static::getContainer()->get(DocumentRenderReadinessCheck::class);
    }

    private function generateDocument(string $documentType, string $orderId)
    {
        $operation = new DocumentGenerateOperation($orderId);

        $result = $this->documentGenerator->generate(
            $documentType,
            [$orderId => $operation],
            $this->context
        )->getSuccess()->first();

        static::assertNotNull($result);
    }

}
