<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Action;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Document\DocumentService;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Content\Flow\Dispatching\Action\GenerateDocumentAction;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;

/**
 * @package business-ops
 *
 * @internal
 * @covers \Shopware\Core\Content\Flow\Dispatching\Action\GenerateDocumentAction
 */
class GenerateDocumentActionTest extends TestCase
{
    /**
     * @var MockObject
     */
    private $documentGenerator;

    /**
     * @var MockObject|DocumentService
     */
    private $documentService;

    /**
     * @var MockObject|StorableFlow
     */
    private $flow;

    private GenerateDocumentAction $action;

    public function setUp(): void
    {
        $this->documentGenerator = $this->getMockBuilder(DocumentGenerator::class)->disableOriginalConstructor()->onlyMethods(['generate'])->getMock();
        $this->documentService = $this->createMock(DocumentService::class);

        $this->action = new GenerateDocumentAction(
            $this->documentService,
            $this->documentGenerator,
            $this->createMock(NumberRangeValueGeneratorInterface::class),
            $this->createMock(Connection::class),
            $this->createMock(LoggerInterface::class),
        );

        $this->flow = $this->createMock(StorableFlow::class);
    }

    public function testRequirements(): void
    {
        static::assertSame(
            [OrderAware::class],
            $this->action->requirements()
        );
    }

    public function testSubscribedEvents(): void
    {
        if (Feature::isActive('v6.5.0.0')) {
            static::assertSame(
                [],
                GenerateDocumentAction::getSubscribedEvents()
            );

            return;
        }

        static::assertSame(
            ['action.generate.document' => 'handle'],
            GenerateDocumentAction::getSubscribedEvents()
        );
    }

    public function testName(): void
    {
        static::assertSame('action.generate.document', GenerateDocumentAction::getName());
    }

    /**
     * @param array<string, mixed> $config
     *
     * @dataProvider actionExecutedProvider
     */
    public function testActionExecuted(array $config, int $expected): void
    {
        $this->flow->expects(static::exactly(2))->method('hasStore')->willReturn(true);
        $this->flow->expects(static::exactly(3))->method('getStore')->willReturn(Uuid::randomHex());
        $this->flow->expects(static::exactly(1))->method('getContext')->willReturn(Context::createDefaultContext());

        $this->flow->expects(static::once())->method('getConfig')->willReturn($config);

        $documentType = $config['documentTypes'][0]['documentType'] ?? $config['documentType'] ?? null;
        $orderId = $this->flow->getStore(OrderAware::ORDER_ID);
        $fileType = $config['documentTypes'][0]['fileType'] ?? $config['fileType'] ?? FileTypes::PDF;
        $conf = $config['documentTypes'][0]['config'] ?? $config['config'] ?? [];
        $static = $config['documentTypes'][0]['static'] ?? $config['static'] ?? false;

        if (Feature::isActive('v6.5.0.0')) {
            $operation = new DocumentGenerateOperation($orderId, $fileType, $conf, null, $static);

            $this->documentService->expects(static::never())->method('create');
            $this->documentGenerator->expects(static::exactly($expected))
                ->method('generate')
                ->with($documentType, [$orderId => $operation], Context::createDefaultContext());

            $this->action->handleFlow($this->flow);

            return;
        }

        $this->documentService->expects(static::exactly($expected))->method('create');
        $this->documentGenerator->expects(static::never())->method('generate');
        $this->action->handleFlow($this->flow);
    }

    public function actionExecutedProvider(): \Generator
    {
        yield 'Generate invoice multi' => [
            [
                'documentTypes' => [
                    [
                        'documentType' => 'invoice',
                        'documentRangerType' => 'document_invoice',
                        'custom' => [
                            'invoiceNumber' => '1100',
                        ],
                        'fileType' => 'pdf',
                        'static' => true,
                    ],
                    [
                        'documentType' => 'invoice',
                        'documentRangerType' => 'document_invoice',
                        'custom' => [
                            'invoiceNumber' => '1100',
                        ],
                        'fileType' => 'pdf',
                        'static' => true,
                    ],
                ],
            ],
            2,
        ];

        yield 'Generate invoice single' => [
            [
                'documentType' => 'invoice',
                'documentRangerType' => 'document_invoice',
                'custom' => [
                    'invoiceNumber' => '1100',
                ],
                'fileType' => 'pdf',
                'static' => true,
            ],
            1,
        ];
    }
}
