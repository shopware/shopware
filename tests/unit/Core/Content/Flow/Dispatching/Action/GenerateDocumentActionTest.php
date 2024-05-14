<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Action;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Document\FileGenerator\FileTypes;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Content\Flow\Dispatching\Action\GenerateDocumentAction;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(GenerateDocumentAction::class)]
class GenerateDocumentActionTest extends TestCase
{
    private MockObject&DocumentGenerator $documentGenerator;

    private GenerateDocumentAction $action;

    protected function setUp(): void
    {
        $this->documentGenerator = $this->getMockBuilder(DocumentGenerator::class)->disableOriginalConstructor()->onlyMethods(['generate'])->getMock();

        $this->action = new GenerateDocumentAction(
            $this->documentGenerator,
            $this->createMock(LoggerInterface::class),
        );
    }

    public function testRequirements(): void
    {
        static::assertSame(
            [OrderAware::class],
            $this->action->requirements()
        );
    }

    public function testName(): void
    {
        static::assertSame('action.generate.document', GenerateDocumentAction::getName());
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('actionExecutedProvider')]
    public function testActionExecuted(array $config, int $expected): void
    {
        $orderId = Uuid::randomHex();
        $flow = new StorableFlow('foo', Context::createDefaultContext(), [], [
            OrderAware::ORDER_ID => $orderId,
        ]);
        $flow->setConfig($config);

        $documentType = $config['documentTypes'][0]['documentType'] ?? $config['documentType'] ?? null;
        $fileType = $config['documentTypes'][0]['fileType'] ?? $config['fileType'] ?? FileTypes::PDF;
        $conf = $config['documentTypes'][0]['config'] ?? $config['config'] ?? [];
        $static = $config['documentTypes'][0]['static'] ?? $config['static'] ?? false;

        $operation = new DocumentGenerateOperation($orderId, $fileType, $conf, null, $static);

        $this->documentGenerator->expects(static::exactly($expected))
            ->method('generate')
            ->with($documentType, [$orderId => $operation], Context::createDefaultContext());

        $this->action->handleFlow($flow);
    }

    public static function actionExecutedProvider(): \Generator
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
