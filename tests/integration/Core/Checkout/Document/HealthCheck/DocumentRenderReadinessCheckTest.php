<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Document\HealthCheck;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\Renderer\DeliveryNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdEmbeddedRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdRenderer;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Document\SystemCheck\DocumentRenderReadinessCheck;
use Shopware\Core\Framework\Context;
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
use Shopware\Tests\Integration\Core\Checkout\Document\DocumentTrait;

/**
 * @internal
 */
#[CoversClass(DocumentRenderReadinessCheck::class)]
#[Package('after-sales')]
class DocumentRenderReadinessCheckTest extends TestCase
{
    use CacheTestBehaviour;
    use DatabaseTransactionBehaviour;
    use DocumentTrait;
    use KernelTestBehaviour;

    private Context $context;

    private Connection $connection;

    private SalesChannelContext $salesChannelContext;

    private DocumentGenerator $documentGenerator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = static::getContainer()->get(Connection::class);
        $this->context = Context::createDefaultContext();
        $this->documentGenerator = static::getContainer()->get(DocumentGenerator::class);

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

    #[DataProvider('documentTypeProvider')]
    public function testDocumentsRenderedSuccessfully(string $documentType): void
    {
        $cart = $this->generateDemoCart(1);
        $orderId = $this->persistCart($cart);

        $sql = 'SELECT count(*) FROM `document`';
        static::assertSame(0, (int) $this->connection->fetchOne($sql));

        $this->generateDocument($documentType, $orderId);

        $healthCheck = $this->createCheck();
        $healthCheckResult = $healthCheck->run();

        static::assertSame(1, (int) $this->connection->fetchOne($sql));

        static::assertTrue($healthCheckResult->healthy);
        static::assertSame(Status::OK, $healthCheckResult->status);
        static::assertSame('All documents rendered successfully.', $healthCheckResult->message);
    }

    public static function documentTypeProvider(): \Generator
    {
        yield 'invoice' => [
            'documentType' => InvoiceRenderer::TYPE,
        ];
        yield 'delivery note' => [
            'documentType' => DeliveryNoteRenderer::TYPE,
        ];
        yield 'zugferd ' => [
            'documentType' => ZugferdRenderer::TYPE,
        ];
        yield 'zugferd embedded' => [
            'documentType' => ZugferdEmbeddedRenderer::TYPE,
        ];
    }

    private function createCheck(): DocumentRenderReadinessCheck
    {
        return static::getContainer()->get(DocumentRenderReadinessCheck::class);
    }

    private function generateDocument(string $documentType, string $orderId): void
    {
        $operation = new DocumentGenerateOperation($orderId);

        $result = $this->documentGenerator->generate(
            $documentType,
            [$orderId => $operation],
            Context::createDefaultContext()
        )->getSuccess()->first();

        static::assertNotNull($result);
    }
}
