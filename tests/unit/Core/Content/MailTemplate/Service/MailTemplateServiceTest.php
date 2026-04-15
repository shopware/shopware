<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MailTemplate\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Mail\Payload\MailPayload;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Content\Mail\Service\MailAttachmentsConfig;
use Shopware\Core\Content\MailTemplate\MailTemplateCollection;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateException;
use Shopware\Core\Content\MailTemplate\Request\GetDataAndSendRequest;
use Shopware\Core\Content\MailTemplate\Request\PreviewRequest;
use Shopware\Core\Content\MailTemplate\Service\MailDataProvider;
use Shopware\Core\Content\MailTemplate\Service\MailDataSimulator;
use Shopware\Core\Content\MailTemplate\Service\MailTemplateService;
use Shopware\Core\Content\MailTemplate\Validation\MailTemplateRenderError;
use Shopware\Core\Content\MailTemplate\Validation\MailTemplateRenderSuccess;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Mime\Email;

/**
 * @internal
 */
#[CoversClass(MailTemplateService::class)]
#[Package('after-sales')]
class MailTemplateServiceTest extends TestCase
{
    private AbstractMailService&MockObject $mailService;

    private MailDataProvider&MockObject $mailDataProvider;

    private StringTemplateRenderer&MockObject $templateRenderer;

    private MailDataSimulator&MockObject $mailDataSimulator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailService = $this->createMock(AbstractMailService::class);
        $this->mailDataProvider = $this->createMock(MailDataProvider::class);
        $this->templateRenderer = $this->createMock(StringTemplateRenderer::class);
        $this->mailDataSimulator = $this->createMock(MailDataSimulator::class);
    }

    public function testLoadTemplate(): void
    {
        $mailTemplate = $this->createMailTemplate();
        /** @var StaticEntityRepository<MailTemplateCollection> $mailTemplateRepository */
        $mailTemplateRepository = new StaticEntityRepository([new MailTemplateCollection([$mailTemplate])]);

        $mailTemplateService = $this->createService(
            $mailTemplateRepository
        );

        $loadedMailTemplate = $mailTemplateService->loadTemplate($mailTemplate->getId(), Context::createDefaultContext());

        static::assertSame($mailTemplate, $loadedMailTemplate);
    }

    public function testLoadUnknownTemplate(): void
    {
        /** @var StaticEntityRepository<MailTemplateCollection> $mailTemplateRepository */
        $mailTemplateRepository = new StaticEntityRepository([new MailTemplateCollection()]);

        $mailTemplateService = $this->createService(
            $mailTemplateRepository
        );

        static::expectException(MailTemplateException::class);
        static::expectExceptionMessage('Mail Template not found.');

        $mailTemplateService->loadTemplate(Uuid::randomHex(), Context::createDefaultContext());
    }

    public function testSimulateUsesSimulatorTemplateDataAndCollectsRenderResults(): void
    {
        $context = Context::createDefaultContext();

        $this->mailDataSimulator->expects($this->once())
            ->method('getTemplateData')
            ->with('checkout.order.placed', $context)
            ->willReturn(['order' => ['id' => 'order-id']]);

        $this->templateRenderer->expects($this->once())->method('enableTestMode');
        $this->templateRenderer->expects($this->once())->method('disableTestMode');
        $this->templateRenderer->expects($this->exactly(2))
            ->method('render')
            ->willReturnCallback(
                static function (string $content): string {
                    if ($content === 'broken') {
                        throw new \RuntimeException('broken template');
                    }

                    return 'rendered: ' . $content;
                }
            );

        $mailTemplateService = $this->createService();

        $rendered = $mailTemplateService->simulate(
            [
                'subject' => 'hello',
                'contentHtml' => 'broken',
            ],
            'checkout.order.placed',
            $context
        );

        $subject = $rendered->get('subject');
        static::assertInstanceOf(MailTemplateRenderSuccess::class, $subject);
        static::assertSame('rendered: hello', $subject->getContent());

        $contentHtml = $rendered->get('contentHtml');
        static::assertInstanceOf(MailTemplateRenderError::class, $contentHtml);
        static::assertSame('broken template', $contentHtml->getContent());
    }

    public function testPreviewUsesProviderDataAndTemplateContent(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createMailTemplate();
        $request = new PreviewRequest($mailTemplate, ['order' => 'order-id'], ['foo' => 'bar']);

        $this->mailDataProvider->expects($this->once())
            ->method('getTemplateData')
            ->with($mailTemplate, ['order' => 'order-id'], $context, ['foo' => 'bar'])
            ->willReturn(['foo' => 'bar']);

        $this->templateRenderer->expects($this->once())->method('enableTestMode');
        $this->templateRenderer->expects($this->once())->method('disableTestMode');
        $this->templateRenderer->expects($this->exactly(4))
            ->method('render')
            ->willReturnCallback(static fn (string $value): string => 'rendered: ' . $value);

        $mailTemplateService = $this->createService();

        $rendered = $mailTemplateService->preview($request, $context);

        static::assertSame('rendered: subject', $rendered->get('subject')?->getContent());
        static::assertSame('rendered: sender', $rendered->get('senderName')?->getContent());
        static::assertSame('rendered: <p>html</p>', $rendered->get('contentHtml')?->getContent());
        static::assertSame('rendered: plain', $rendered->get('contentPlain')?->getContent());
    }

    public function testPreviewDoesNotUseTestModeInStrictMode(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createMailTemplate();

        $this->mailDataProvider->method('getTemplateData')->willReturn([]);

        $this->templateRenderer->expects($this->never())->method('enableTestMode');
        $this->templateRenderer->expects($this->never())->method('disableTestMode');
        $this->templateRenderer->expects($this->exactly(4))
            ->method('render')
            ->willReturn('rendered');

        $mailTemplateService = $this->createService();

        $rendered = $mailTemplateService->preview(new PreviewRequest($mailTemplate), $context, true);

        static::assertCount(4, $rendered);
    }

    public function testGetTemplateDataAndSendUsesProviderDataAndTemplateForAttachments(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createMailTemplate();
        $mailPayload = new MailPayload(
            recipients: ['test@example.com' => 'Test'],
            subject: 'Subject',
            senderName: 'Shopware',
            documentIds: ['document-id'],
            mediaIds: ['media-id']
        );
        $request = new GetDataAndSendRequest($mailTemplate, ['order' => 'order-id'], ['foo' => 'bar'], $mailPayload);

        $this->mailDataProvider->expects($this->once())
            ->method('getTemplateData')
            ->with($mailTemplate, ['order' => 'order-id'], $context, ['foo' => 'bar'])
            ->willReturn(['order' => ['id' => 'order-id']]);

        $this->mailService->expects($this->once())
            ->method('send')
            ->with(
                static::callback(function (array $data) use ($mailTemplate): bool {
                    static::assertArrayHasKey('attachmentsConfig', $data);
                    static::assertInstanceOf(MailAttachmentsConfig::class, $data['attachmentsConfig']);
                    static::assertSame($mailTemplate, $data['attachmentsConfig']->getMailTemplate());
                    static::assertSame('order-id', $data['attachmentsConfig']->getOrderId());
                    static::assertSame(['document-id'], $data['attachmentsConfig']->getExtension()->getDocumentIds());
                    static::assertSame(['media-id'], $data['attachmentsConfig']->getExtension()->getMediaIds());

                    return true;
                }),
                $context,
                ['order' => ['id' => 'order-id']]
            )
            ->willReturn(null);

        $mailTemplateService = $this->createService();

        static::assertNull($mailTemplateService->getTemplateDataAndSend($request, $context));
    }

    public function testSendBuildsAttachmentsConfigFromOrderEntityWithoutMailTemplate(): void
    {
        $context = Context::createDefaultContext();
        $order = new OrderEntity();
        $order->setId('order-id');

        $this->mailService->expects($this->once())
            ->method('send')
            ->with(
                static::callback(function (array $data): bool {
                    static::assertArrayHasKey('attachmentsConfig', $data);
                    static::assertInstanceOf(MailAttachmentsConfig::class, $data['attachmentsConfig']);
                    static::assertSame('order-id', $data['attachmentsConfig']->getOrderId());

                    return true;
                }),
                $context,
                ['order' => $order]
            )
            ->willReturn($this->createMock(Email::class));

        $mailTemplateService = $this->createService();

        $result = $mailTemplateService->send(
            new MailPayload(subject: 'Subject', senderName: 'Sender'),
            $context,
            ['order' => $order]
        );

        static::assertInstanceOf(Email::class, $result);
    }

    /**
     * @param array<array{fieldName: string, hasChildren: bool}> $expected
     */
    #[DataProvider('fieldPathProvider')]
    public function testAvailableVariables(string $fieldPath, array $expected): void
    {
        $this->mailDataSimulator->expects($this->once())
            ->method('getTemplateData')
            ->with('review_form.send', static::isInstanceOf(Context::class))
            ->willReturn([
                'foo' => 'value',
                'bar' => [
                    'foobar' => 'value',
                    'baz' => [
                        'key' => 'value',
                    ],
                    'struct' => new ArrayEntity([
                        'units' => new ArrayEntity([
                            'length' => ['name' => 'cm'],
                            'weight' => ['name' => 'kg'],
                        ]),
                    ]),
                ],
                'topLevelStruct' => new ArrayEntity([
                    'units' => ['length' => 'cm'],
                ]),
                'collectionStruct' => new ArrayEntity([
                    'items' => new class([new ArrayEntity([
                        'name' => 'first item',
                        'nested' => ['value' => 'nested value'],
                    ])]) extends Collection {
                        protected function getExpectedClass(): ?string
                        {
                            return ArrayEntity::class;
                        }
                    },
                ]),
            ]);

        $mailTemplateService = $this->createService();

        $result = $mailTemplateService->getAvailableVariables('review_form.send', Context::createDefaultContext(), $fieldPath);

        static::assertSame($expected, $result);
    }

    public static function fieldPathProvider(): \Generator
    {
        yield 'empty field path' => [
            'fieldPath' => '',
            'expected' => [
                [
                    'fieldName' => 'foo',
                    'hasChildren' => false,
                ],
                [
                    'fieldName' => 'bar',
                    'hasChildren' => true,
                ],
                [
                    'fieldName' => 'topLevelStruct',
                    'hasChildren' => true,
                ],
            ],
        ];

        yield 'valid field path' => [
            'fieldPath' => 'bar',
            'expected' => [
                [
                    'fieldName' => 'foobar',
                    'hasChildren' => false,
                ],
                [
                    'fieldName' => 'baz',
                    'hasChildren' => true,
                ],
                [
                    'fieldName' => 'struct',
                    'hasChildren' => true,
                ],
            ],
        ];

        yield 'valid field path on element without children' => [
            'fieldPath' => 'foo',
            'expected' => [],
        ];

        yield 'nested field path' => [
            'fieldPath' => 'bar.baz',
            'expected' => [
                [
                    'fieldName' => 'key',
                    'hasChildren' => false,
                ],
            ],
        ];

        yield 'unknown field path' => [
            'fieldPath' => 'unknown',
            'expected' => [],
        ];

        yield 'field path to struct' => [
            'fieldPath' => 'bar.struct',
            'expected' => [
                [
                    'fieldName' => 'extensions',
                    'hasChildren' => false,
                ],
                [
                    'fieldName' => 'translated',
                    'hasChildren' => false,
                ],
                [
                    'fieldName' => 'units',
                    'hasChildren' => true,
                ],
            ],
        ];

        yield 'access struct property' => [
            'fieldPath' => 'bar.struct.units',
            'expected' => [
                [
                    'fieldName' => 'extensions',
                    'hasChildren' => false,
                ],
                [
                    'fieldName' => 'translated',
                    'hasChildren' => false,
                ],
                [
                    'fieldName' => 'length',
                    'hasChildren' => true,
                ],
                [
                    'fieldName' => 'weight',
                    'hasChildren' => true,
                ],
            ],
        ];

        yield 'collection field path' => [
            'fieldPath' => 'collectionStruct.items',
            'expected' => [
                [
                    'fieldName' => 'first',
                    'hasChildren' => true,
                ],
            ],
        ];

        yield 'access collection first property' => [
            'fieldPath' => 'collectionStruct.items.first',
            'expected' => [
                [
                    'fieldName' => 'extensions',
                    'hasChildren' => false,
                ],
                [
                    'fieldName' => 'translated',
                    'hasChildren' => false,
                ],
                [
                    'fieldName' => 'name',
                    'hasChildren' => false,
                ],
                [
                    'fieldName' => 'nested',
                    'hasChildren' => true,
                ],
            ],
        ];
    }

    /**
     * @param StaticEntityRepository<MailTemplateCollection>|null $mailTemplateRepository
     */
    private function createService(?StaticEntityRepository $mailTemplateRepository = null): MailTemplateService
    {
        /** @var StaticEntityRepository<MailTemplateCollection> $mailTemplateRepository */
        $mailTemplateRepository ??= new StaticEntityRepository([]);

        return new MailTemplateService(
            $this->mailService,
            $this->mailDataProvider,
            $mailTemplateRepository,
            $this->templateRenderer,
            $this->mailDataSimulator,
        );
    }

    private function createMailTemplate(): MailTemplateEntity
    {
        $mailTemplate = new MailTemplateEntity();
        $mailTemplate->setId(Uuid::randomHex());
        $mailTemplate->setSubject('subject');
        $mailTemplate->setSenderName('sender');
        $mailTemplate->setContentHtml('<p>html</p>');
        $mailTemplate->setContentPlain('plain');

        return $mailTemplate;
    }
}
