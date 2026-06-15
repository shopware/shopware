<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MailTemplate\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateCollection;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateException;
use Shopware\Core\Content\MailTemplate\Request\PreviewRequest;
use Shopware\Core\Content\MailTemplate\Request\SimulateRequest;
use Shopware\Core\Content\MailTemplate\Service\Event\MailTemplateRenderContextEvent;
use Shopware\Core\Content\MailTemplate\Service\MailDataProvider;
use Shopware\Core\Content\MailTemplate\Service\MailDataSimulator;
use Shopware\Core\Content\MailTemplate\Service\MailTemplateContentBuilder;
use Shopware\Core\Content\MailTemplate\Service\MailTemplateService;
use Shopware\Core\Content\MailTemplate\Validation\MailTemplateRenderResult;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(MailTemplateService::class)]
#[Package('after-sales')]
class MailTemplateServiceTest extends TestCase
{
    private MailDataProvider&MockObject $mailDataProvider;

    private StringTemplateRenderer&MockObject $templateRenderer;

    private MailDataSimulator&MockObject $mailDataSimulator;

    private MailTemplateContentBuilder $mailTemplateContentBuilder;

    private EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailDataProvider = $this->createMock(MailDataProvider::class);
        $this->templateRenderer = $this->createMock(StringTemplateRenderer::class);
        $this->mailDataSimulator = $this->createMock(MailDataSimulator::class);
        $this->mailTemplateContentBuilder = new MailTemplateContentBuilder();
        $this->eventDispatcher = new EventDispatcher();
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

        $this->expectExceptionObject(MailTemplateException::templateNotFound());

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
                static function (string $content, array $templateData, Context $context, bool $escape): string {
                    if ($content === 'broken') {
                        throw new \RuntimeException('broken template');
                    }

                    return \sprintf('rendered:%s:%s', $escape ? 'escaped' : 'raw', $content);
                }
            );

        $mailTemplateService = $this->createService();

        $rendered = $mailTemplateService->simulate(new SimulateRequest(
            templateParts: [
                'subject' => 'hello',
                'contentHtml' => 'broken',
            ],
            eventName: 'checkout.order.placed',
            strictRendering: false,
        ), $context);

        $subject = $rendered['subject'];
        static::assertInstanceOf(MailTemplateRenderResult::class, $subject);
        static::assertSame(MailTemplateRenderResult::TYPE_SUCCESS, $subject->getType());
        static::assertSame('rendered:raw:hello', $subject->getContent());

        $contentHtml = $rendered['contentHtml'];
        static::assertInstanceOf(MailTemplateRenderResult::class, $contentHtml);
        static::assertSame(MailTemplateRenderResult::TYPE_ERROR, $contentHtml->getType());
        static::assertSame('broken template', $contentHtml->getContent());
        static::assertSame('Error', $contentHtml->getErrorTitle());
        static::assertSame('broken template', $contentHtml->getErrorMessage());
    }

    public function testSimulateUsesSelectedSalesChannelAndDoesNotUseTestModeInStrictMode(): void
    {
        $context = Context::createDefaultContext();
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());

        $this->mailDataSimulator->expects($this->once())
            ->method('getTemplateData')
            ->with('checkout.order.placed', $context, $salesChannel)
            ->willReturn(['order' => ['id' => 'order-id']]);

        $this->templateRenderer->expects($this->never())->method('enableTestMode');
        $this->templateRenderer->expects($this->never())->method('disableTestMode');
        $this->templateRenderer->expects($this->once())
            ->method('render')
            ->with('<p>{{ order.id }}</p>', [
                'order' => ['id' => 'order-id'],
                'salesChannel' => $salesChannel,
                'salesChannelId' => $salesChannel->getId(),
            ], $context, true)
            ->willReturn('<p>order-id</p>');

        $mailTemplateService = $this->createService();

        $rendered = $mailTemplateService->simulate(new SimulateRequest(
            templateParts: ['contentHtml' => '<p>{{ order.id }}</p>'],
            eventName: 'checkout.order.placed',
            salesChannel: $salesChannel,
            strictRendering: true,
        ), $context);

        static::assertSame(MailTemplateRenderResult::TYPE_SUCCESS, $rendered['contentHtml']->getType());
        static::assertSame('<p>order-id</p>', $rendered['contentHtml']->getContent());
    }

    public function testSimulateDispatchesRenderContextEvent(): void
    {
        $context = Context::createDefaultContext();
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());

        $this->mailDataSimulator->expects($this->once())
            ->method('getTemplateData')
            ->with('checkout.order.placed', $context, $salesChannel)
            ->willReturn(['order' => ['id' => 'order-id']]);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (MailTemplateRenderContextEvent $event) use ($context, $salesChannel): MailTemplateRenderContextEvent {
                static::assertSame($context, $event->getContext());
                static::assertSame($salesChannel, $event->getSalesChannel());
                static::assertSame([
                    'order' => ['id' => 'order-id'],
                    'salesChannel' => $salesChannel,
                    'salesChannelId' => $salesChannel->getId(),
                ], $event->getTemplateData());

                $event->addTemplateData('themeId', 'theme-id');

                return $event;
            });

        $this->templateRenderer->expects($this->once())
            ->method('render')
            ->with('hello', [
                'order' => ['id' => 'order-id'],
                'salesChannel' => $salesChannel,
                'salesChannelId' => $salesChannel->getId(),
                'themeId' => 'theme-id',
            ], $context, false)
            ->willReturn('rendered');

        $mailTemplateService = $this->createService(eventDispatcher: $eventDispatcher);

        $rendered = $mailTemplateService->simulate(new SimulateRequest(
            templateParts: ['subject' => 'hello'],
            eventName: 'checkout.order.placed',
            salesChannel: $salesChannel,
        ), $context);

        static::assertSame('rendered', $rendered['subject']->getContent());
    }

    public function testPreviewUsesProviderDataAndTemplateContent(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createMailTemplate();
        $request = new PreviewRequest(
            mailTemplate: $mailTemplate,
            entityMapping: ['order' => 'order-id'],
            templateData: ['foo' => 'bar'],
        );

        $this->mailDataProvider->expects($this->once())
            ->method('getTemplateData')
            ->with($mailTemplate, ['order' => 'order-id'], $context, ['foo' => 'bar'])
            ->willReturn(['foo' => 'bar']);

        $this->templateRenderer->expects($this->once())->method('enableTestMode');
        $this->templateRenderer->expects($this->once())->method('disableTestMode');
        $this->templateRenderer->expects($this->exactly(4))
            ->method('render')
            ->willReturnCallback(
                static fn (string $value, array $templateData, Context $context, bool $escape): string => \sprintf(
                    'rendered:%s:%s',
                    $escape ? 'escaped' : 'raw',
                    $value,
                )
            );

        $mailTemplateService = $this->createService();

        $rendered = $mailTemplateService->preview($request, $context);

        static::assertSame('rendered:raw:subject', $rendered['subject']->getContent());
        static::assertSame('rendered:raw:sender', $rendered['senderName']->getContent());
        static::assertSame('rendered:escaped:<p>html</p>', $rendered['contentHtml']->getContent());
        static::assertSame('rendered:raw:plain', $rendered['contentPlain']->getContent());
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

        $rendered = $mailTemplateService->preview(new PreviewRequest($mailTemplate, strictRendering: true), $context);

        static::assertCount(4, $rendered);
    }

    public function testPreviewCanIncludeHeaderFooter(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createMailTemplate();
        $mailHeaderFooter = new MailHeaderFooterEntity();
        $mailHeaderFooter->setTranslated([
            'headerHtml' => '<header>{{ foo }}</header>',
            'footerHtml' => '<footer>{{ foo }}</footer>',
            'headerPlain' => 'H {{ foo }} ',
            'footerPlain' => ' F {{ foo }}',
        ]);

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());
        $salesChannel->setMailHeaderFooter($mailHeaderFooter);

        $request = new PreviewRequest(
            mailTemplate: $mailTemplate,
            salesChannel: $salesChannel,
            templateData: ['foo' => 'bar'],
            includeHeaderFooter: true,
        );

        $this->mailDataProvider->expects($this->once())
            ->method('getTemplateData')
            ->with($mailTemplate, [], $context, ['foo' => 'bar'])
            ->willReturn(['foo' => 'bar']);

        $this->templateRenderer->expects($this->once())->method('enableTestMode');
        $this->templateRenderer->expects($this->once())->method('disableTestMode');
        $this->templateRenderer->expects($this->exactly(4))
            ->method('render')
            ->willReturnCallback(
                static fn (string $value, array $templateData, Context $context, bool $escape): string => \sprintf(
                    'rendered:%s:%s',
                    $escape ? 'escaped' : 'raw',
                    $value,
                )
            );

        $mailTemplateService = $this->createService();

        $rendered = $mailTemplateService->preview($request, $context);

        static::assertSame('rendered:escaped:<header>{{ foo }}</header><p>html</p><footer>{{ foo }}</footer>', $rendered['contentHtml']->getContent());
        static::assertSame('rendered:raw:H {{ foo }} plain F {{ foo }}', $rendered['contentPlain']->getContent());
    }

    public function testPreviewDispatchesRenderContextEvent(): void
    {
        $context = Context::createDefaultContext();
        $mailTemplate = $this->createMailTemplate();
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());

        $request = new PreviewRequest(
            mailTemplate: $mailTemplate,
            salesChannel: $salesChannel,
            templateData: ['foo' => 'bar'],
            strictRendering: true,
        );

        $this->mailDataProvider->expects($this->once())
            ->method('getTemplateData')
            ->with($mailTemplate, [], $context, ['foo' => 'bar'])
            ->willReturn(['foo' => 'bar']);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (MailTemplateRenderContextEvent $event) use ($context, $salesChannel): MailTemplateRenderContextEvent {
                static::assertSame($context, $event->getContext());
                static::assertSame($salesChannel, $event->getSalesChannel());
                static::assertSame([
                    'foo' => 'bar',
                    'salesChannel' => $salesChannel,
                    'salesChannelId' => $salesChannel->getId(),
                ], $event->getTemplateData());

                $event->addTemplateData('salesChannelContext', 'context');

                return $event;
            });

        $this->templateRenderer->expects($this->exactly(4))
            ->method('render')
            ->willReturnCallback(
                static function (string $value, array $templateData) use ($salesChannel): string {
                    static::assertSame([
                        'foo' => 'bar',
                        'salesChannel' => $salesChannel,
                        'salesChannelId' => $salesChannel->getId(),
                        'salesChannelContext' => 'context',
                    ], $templateData);

                    return 'rendered:' . $value;
                }
            );

        $mailTemplateService = $this->createService(eventDispatcher: $eventDispatcher);

        $rendered = $mailTemplateService->preview($request, $context);

        static::assertSame('rendered:subject', $rendered['subject']->getContent());
        static::assertSame('rendered:sender', $rendered['senderName']->getContent());
        static::assertSame('rendered:<p>html</p>', $rendered['contentHtml']->getContent());
        static::assertSame('rendered:plain', $rendered['contentPlain']->getContent());
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
                    'items' => new class([new ArrayEntity(['name' => 'first item', 'nested' => ['value' => 'nested value']])]) extends Collection {
                        protected function getExpectedClass(): string
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
                [
                    'fieldName' => 'collectionStruct',
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
    private function createService(
        ?StaticEntityRepository $mailTemplateRepository = null,
        ?EventDispatcherInterface $eventDispatcher = null
    ): MailTemplateService {
        /** @var StaticEntityRepository<MailTemplateCollection> $mailTemplateRepository */
        $mailTemplateRepository ??= new StaticEntityRepository([]);

        return new MailTemplateService(
            $mailTemplateRepository,
            $this->templateRenderer,
            $this->mailDataProvider,
            $this->mailDataSimulator,
            $this->mailTemplateContentBuilder,
            $eventDispatcher ?? $this->eventDispatcher,
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
