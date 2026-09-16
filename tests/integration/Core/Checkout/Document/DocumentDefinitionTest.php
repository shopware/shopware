<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Document;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Integration\Traits\OrderFixture;

/**
 * @internal
 */
#[Package('after-sales')]
class DocumentDefinitionTest extends TestCase
{
    use IntegrationTestBehaviour;
    use OrderFixture;

    /**
     * @var EntityRepository<DocumentCollection>
     */
    private EntityRepository $documentRepository;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->documentRepository = static::getContainer()->get('document.repository');
        $this->orderRepository = static::getContainer()->get('order.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testDocumentWriteCarriesItsOrderAsParent(): void
    {
        $orderId = Uuid::randomHex();
        $this->orderRepository->create($this->getOrderData($orderId, $this->context), $this->context);

        $documentId = Uuid::randomHex();
        $result = $this->documentRepository->create([$this->documentPayload($documentId, $orderId)], $this->context);

        static::assertContains($documentId, $result->getPrimaryKeys('document'));
        static::assertSame([$orderId], $result->getPrimaryKeys('order'));
    }

    public function testDocumentWriteWithoutOrderCarriesNoParent(): void
    {
        Feature::skipTestIfInActive('DOCUMENT_GENERATION_REWORK', $this);

        $documentId = Uuid::randomHex();
        $result = $this->documentRepository->create([$this->documentPayload($documentId, null)], $this->context);

        static::assertContains($documentId, $result->getPrimaryKeys('document'));
        static::assertSame([], $result->getPrimaryKeys('order'));
    }

    /**
     * @return array<string, mixed>
     */
    private function documentPayload(string $documentId, ?string $orderId): array
    {
        $documentTypeId = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT LOWER(HEX(id)) FROM document_type WHERE technical_name = :name',
            ['name' => 'invoice']
        );
        static::assertIsString($documentTypeId);

        return [
            'id' => $documentId,
            'orderId' => $orderId,
            'orderVersionId' => $orderId === null ? null : Defaults::LIVE_VERSION,
            'documentTypeId' => $documentTypeId,
            'typeName' => 'invoice',
            'deepLinkCode' => Random::getAlphanumericString(32),
            'sent' => false,
            'static' => false,
            'config' => ['documentNumber' => '1000'],
        ];
    }
}
