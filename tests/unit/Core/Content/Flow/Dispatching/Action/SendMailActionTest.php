<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Action;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Flow\Dispatching\Action\FlowMailVariables;
use Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopware\Core\Content\Flow\Dispatching\FlowState;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Struct\Sequence;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Content\Mail\Service\MailAttachmentsConfig;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeCollection;
use Shopware\Core\Content\MailTemplate\Exception\MailEventConfigurationException;
use Shopware\Core\Content\MailTemplate\MailTemplateCollection;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\CustomerAware;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\LanguageAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\TestDefaults;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(SendMailAction::class)]
class SendMailActionTest extends TestCase
{
    private MailTemplateEntity $mailTemplate;

    /**
     * @var AbstractMailService&Stub
     */
    private AbstractMailService $mailService;

    /**
     * @var EntityRepository<MailTemplateCollection>&Stub
     */
    private EntityRepository $mailTemplateRepository;

    /**
     * @var EntityRepository<MailTemplateTypeCollection>&Stub
     */
    private EntityRepository $mailTemplateTypeRepository;

    /**
     * @var LoggerInterface&Stub
     */
    private LoggerInterface $logger;

    private SendMailAction $action;

    protected function setUp(): void
    {
        $this->mailTemplate = new MailTemplateEntity();
        $this->mailService = static::createStub(AbstractMailService::class);
        $this->mailTemplateRepository = static::createStub(EntityRepository::class);
        $this->mailTemplateTypeRepository = static::createStub(EntityRepository::class);
        $this->logger = static::createStub(LoggerInterface::class);

        $this->action = $this->createAction();
    }

    public function testRequirements(): void
    {
        static::assertSame(
            [MailAware::class],
            $this->action->requirements()
        );
    }

    public function testName(): void
    {
        static::assertSame('action.mail.send', SendMailAction::getName());
    }

    /**
     * @deprecated tag:v6.8.0 - Removed with mail template sample updates.
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    #[DataProvider('mailTemplateTypeProvider')]
    public function testUpdateMailTemplateType(MailTemplateTypeUpdateProvider $provider): void
    {
        $context = Context::createDefaultContext();

        $connection = static::createStub(Connection::class);
        $encoder = static::createStub(JsonEntityEncoder::class);
        $encoder->method('encode')->willReturn(['encoded']);

        $mailTemplateRepository = $this->createMock(EntityRepository::class);
        $mailTemplateTypeRepository = $this->createMock(EntityRepository::class);
        $mailService = $this->createMock(AbstractMailService::class);

        $action = $this->createAction(
            mailService: $mailService,
            mailTemplateRepository: $mailTemplateRepository,
            mailTemplateTypeRepository: $mailTemplateTypeRepository,
            connection: $connection,
            encoder: $encoder,
            updateMailTemplateType: $provider->updateMailTemplateTypeParam,
        );

        $mailTemplateId = Uuid::randomHex();
        $this->mailTemplate->setId($mailTemplateId);
        $this->mailTemplate->setSenderName('Phuoc');
        $config = array_filter([
            'mailTemplateId' => $mailTemplateId,
            'recipient' => ['type' => 'customer'],
            'documentTypeIds' => null,
            'replyTo' => 'foo@example.com',
        ]);

        $this->mailTemplate->setMailTemplateTypeId($provider->mailTemplateTypeId);

        $expected = [
            'data' => [
                'recipients' => [
                    'email' => 'firstName lastName',
                ],
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'templateId' => $mailTemplateId,
            ],
            'context' => $context,
        ];

        $templateData = new MailRecipientStruct($expected['data']['recipients']);

        $flow = new StorableFlow(
            '',
            $expected['context'],
            []
        );
        $state = new FlowState();
        $state->currentSequence = new Sequence();
        $state->currentSequence->sequenceId = Uuid::randomHex();
        $state->currentSequence->flowId = Uuid::randomHex();
        $state->flowId = $state->currentSequence->flowId;
        $flow->setFlowState($state);
        $flow->setData(MailAware::MAIL_STRUCT, $templateData);
        $flow->setData(MailAware::SALES_CHANNEL_ID, TestDefaults::SALES_CHANNEL);

        $customer = new CustomerEntity();
        $customer->setId(Uuid::randomHex());
        // needed so `_entityName` property is set correctly
        $customer->getApiAlias();

        $flow->setData(CustomerAware::CUSTOMER, $customer);
        $flow->setConfig($config);

        if ($provider->sensitive) {
            $flow->setData('sensitive', true);
            $flow->setData(FlowMailVariables::RESET_URL, 'BODY-SECRET');
        }

        $mailService->expects($this->once())->method('send')->with(
            static::anything(),
            $context,
            ['eventName' => '', ...$flow->data()],
        );

        $entitySearchResult = $this->createMock(EntitySearchResult::class);
        $entitySearchResult->expects($this->once())
            ->method('getEntities')
            ->willReturn(new MailTemplateCollection([$this->mailTemplate]));

        $mailTemplateRepository->expects($this->once())
            ->method('search')
            ->willReturn($entitySearchResult);

        if (!Feature::isActive('v6.8.0.0') && $provider->mailTemplateTypeId && $provider->updateMailTemplateTypeParam && !$provider->sensitive) {
            $mailTemplateTypeRepository->expects($this->once())->method('update')->with([
                [
                    'id' => $provider->mailTemplateTypeId,
                    'templateData' => [
                        'mailStruct' => $templateData,
                        'salesChannelId' => TestDefaults::SALES_CHANNEL,
                        'customer' => ['encoded'],
                    ],
                ],
            ], $context);
        } else {
            $mailTemplateTypeRepository->expects($this->never())->method('update');
        }

        $action->handleFlow($flow);
    }

    /**
     * @param array<string, string> $exptectedReplyTo
     */
    #[DataProvider('replyToProvider')]
    public function testActionExecuted(?string $replyTo, array $exptectedReplyTo = []): void
    {
        $orderId = Uuid::randomHex();
        $mailTemplateId = Uuid::randomHex();
        $this->mailTemplate->setId($mailTemplateId);
        $this->mailTemplate->setSenderName('Phuoc');
        $config = array_filter([
            'mailTemplateId' => $mailTemplateId,
            'recipient' => ['type' => 'customer'],
            'documentTypeIds' => null,
            'replyTo' => $replyTo,
        ]);

        $expected = [
            'data' => [
                'recipients' => [
                    'email' => 'firstName lastName',
                ],
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'templateId' => $mailTemplateId,
                'customFields' => null,
                'contentHtml' => null,
                'contentPlain' => null,
                'subject' => null,
                'mediaIds' => [],
                'senderName' => null,
                'languageId' => null,
                'timezone' => null,
                'attachmentsConfig' => new MailAttachmentsConfig(
                    Context::createDefaultContext(),
                    $this->mailTemplate,
                    new MailSendSubscriberConfig(false, [], []),
                    $config,
                    $orderId
                ),
            ],
            'context' => Context::createDefaultContext(),
        ];

        $templateData = new MailRecipientStruct($expected['data']['recipients']);

        $expected['data'] = array_merge($expected['data'], $exptectedReplyTo);

        $flow = new StorableFlow(
            '',
            $expected['context'],
            [
                MailAware::MAIL_STRUCT => [
                    'recipients' => [
                        'email' => 'firstName lastName',
                    ],
                ],
                MailAware::SALES_CHANNEL_ID => TestDefaults::SALES_CHANNEL,
                OrderAware::ORDER_ID => $orderId,
            ]
        );
        $flow->setData(MailAware::MAIL_STRUCT, $templateData);
        $flow->setData(MailAware::SALES_CHANNEL_ID, TestDefaults::SALES_CHANNEL);
        $flow->setData(OrderAware::ORDER_ID, $orderId);
        $flow->setData(FlowMailVariables::CONTACT_FORM_DATA, [
            'email' => 'customer@example.com',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
        ]);

        $flow->setConfig($config);

        $entitySearchResult = $this->createMock(EntitySearchResult::class);
        $entitySearchResult->expects($this->once())
            ->method('getEntities')
            ->willReturn(new MailTemplateCollection([$this->mailTemplate]));

        $mailTemplateRepository = $this->createMock(EntityRepository::class);
        $mailTemplateRepository->expects($this->once())
            ->method('search')
            ->willReturn($entitySearchResult);

        $mailService = $this->createMock(AbstractMailService::class);
        $mailService->expects($this->once())
            ->method('send')
            ->with(
                $expected['data'],
                $expected['context'],
                [
                    'eventName' => $flow->getName(),
                    'mailStruct' => $templateData,
                    'salesChannelId' => TestDefaults::SALES_CHANNEL,
                    'orderId' => $orderId,
                    'contactFormData' => [
                        'email' => 'customer@example.com',
                        'firstName' => 'Max',
                        'lastName' => 'Mustermann',
                    ],
                ],
            );

        $this->createAction(
            mailService: $mailService,
            mailTemplateRepository: $mailTemplateRepository,
        )->handleFlow($flow);
    }

    /**
     * @return iterable<string, array<MailTemplateTypeUpdateProvider>>
     */
    public static function mailTemplateTypeProvider(): iterable
    {
        yield 'mailTemplateTypeUpdate param is false' => [new MailTemplateTypeUpdateProvider(
            updateMailTemplateTypeParam: false,
            mailTemplateTypeId: Uuid::randomHex(),
        )];

        yield 'no mail template type id' => [new MailTemplateTypeUpdateProvider(
            updateMailTemplateTypeParam: true,
            mailTemplateTypeId: null,
        )];

        yield 'mail template type id exists' => [new MailTemplateTypeUpdateProvider(
            updateMailTemplateTypeParam: true,
            mailTemplateTypeId: Uuid::randomHex(),
        )];

        yield 'sensitive mail is sent without persisting its sample data' => [new MailTemplateTypeUpdateProvider(
            updateMailTemplateTypeParam: true,
            mailTemplateTypeId: Uuid::randomHex(),
            sensitive: true,
        )];
    }

    public static function replyToProvider(): \Generator
    {
        yield 'no reply to' => [null];
        yield 'custom reply to' => ['foo@example.com', ['senderMail' => 'foo@example.com']];
        yield 'contact form reply to' => ['contactFormMail', [
            'senderMail' => 'customer@example.com',
            'senderName' => '{% if contactFormData.firstName is defined %}{{ contactFormData.firstName }}{% endif %} {% if contactFormData.lastName is defined %}{{ contactFormData.lastName }}{% endif %}',
        ]];
    }

    public function testActionWithNotAware(): void
    {
        $flow = new StorableFlow('', Context::createDefaultContext(), []);
        $flow->setConfig(array_filter([
            'mailTemplateId' => Uuid::randomHex(),
            'recipient' => ['type' => 'customer'],
            'documentTypeIds' => null,
            'replyTo' => '',
        ]));

        static::expectException(MailEventConfigurationException::class);
        $mailService = $this->createMock(AbstractMailService::class);
        $mailService->expects($this->never())->method('send');

        $this->createAction(mailService: $mailService)->handleFlow($flow);
    }

    public function testActionWithEmptyConfig(): void
    {
        $flow = new StorableFlow('', Context::createDefaultContext(), []);

        static::expectException(MailEventConfigurationException::class);
        $mailService = $this->createMock(AbstractMailService::class);
        $mailService->expects($this->never())->method('send');

        $this->createAction(mailService: $mailService)->handleFlow($flow);
    }

    public function testActionExecutedWithRecipientFromStoreData(): void
    {
        $mailTemplateId = Uuid::randomHex();
        $orderId = Uuid::randomHex();
        $config = array_filter([
            'mailTemplateId' => $mailTemplateId,
            'recipient' => ['type' => 'customer'],
            'documentTypeIds' => null,
        ]);
        $languageId = Uuid::randomHex();

        $expected = [
            'data' => [
                'recipients' => [
                    'email' => 'firstName lastName',
                ],
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'templateId' => $mailTemplateId,
                'customFields' => null,
                'contentHtml' => null,
                'contentPlain' => null,
                'subject' => null,
                'mediaIds' => [],
                'senderName' => null,
                'languageId' => $languageId,
                'timezone' => 'UTC',
                'attachmentsConfig' => new MailAttachmentsConfig(
                    Context::createDefaultContext(),
                    $this->mailTemplate,
                    new MailSendSubscriberConfig(false, [], []),
                    $config,
                    $orderId
                ),
            ],
            'context' => Context::createDefaultContext(),
        ];

        $templateData = new MailRecipientStruct($expected['data']['recipients']);
        $this->mailTemplate->setId($mailTemplateId);

        $flow = new StorableFlow(
            '',
            $expected['context'],
            [
                MailAware::MAIL_STRUCT => [
                    'recipients' => [
                        'email' => 'firstName lastName',
                    ],
                ],
                MailAware::SALES_CHANNEL_ID => TestDefaults::SALES_CHANNEL,
                OrderAware::ORDER_ID => $orderId,
            ]
        );
        $flow->setData(MailAware::MAIL_STRUCT, $templateData);
        $flow->setData(MailAware::SALES_CHANNEL_ID, TestDefaults::SALES_CHANNEL);
        $flow->setData(OrderAware::ORDER_ID, $orderId);
        $flow->setData(LanguageAware::LANGUAGE_ID, $languageId);
        $flow->setData(MailAware::TIMEZONE, 'UTC');
        $flow->setData(FlowMailVariables::CONTACT_FORM_DATA, [
            'email' => 'customer@example.com',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
        ]);

        $flow->setConfig($config);

        $entitySearchResult = $this->createMock(EntitySearchResult::class);
        $entitySearchResult->expects($this->once())
            ->method('getEntities')
            ->willReturn(new MailTemplateCollection([$this->mailTemplate]));

        $mailTemplateRepository = $this->createMock(EntityRepository::class);
        $mailTemplateRepository->expects($this->once())
            ->method('search')
            ->willReturn($entitySearchResult);

        $mailService = $this->createMock(AbstractMailService::class);
        $mailService->expects($this->once())
            ->method('send')
            ->with(
                $expected['data'],
                $expected['context'],
                [
                    'mailStruct' => $templateData,
                    'eventName' => '',
                    'salesChannelId' => TestDefaults::SALES_CHANNEL,
                    'orderId' => $orderId,
                    'contactFormData' => [
                        'email' => 'customer@example.com',
                        'firstName' => 'Max',
                        'lastName' => 'Mustermann',
                    ],
                    'languageId' => $languageId,
                    'timezone' => 'UTC',
                ]
            );

        $this->createAction(
            mailService: $mailService,
            mailTemplateRepository: $mailTemplateRepository,
        )->handleFlow($flow);
    }

    public function testSendFailureDoesNotLogBodyOrExceptionContent(): void
    {
        $context = Context::createDefaultContext();
        $templateId = Uuid::randomHex();
        $this->mailTemplate->setId($templateId);
        $this->mailTemplate->setTranslated([
            'subject' => 'Recovery',
            'contentHtml' => 'BODY-SECRET',
            'contentPlain' => 'BODY-SECRET',
        ]);
        $this->mailTemplateRepository->method('search')->willReturn(new EntitySearchResult(
            'mail_template',
            1,
            new MailTemplateCollection([$this->mailTemplate]),
            null,
            new Criteria(),
            $context,
        ));
        $this->mailService->method('send')->willThrowException(new \RuntimeException('BODY-SECRET'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with(
            'Could not send mail.',
            static::callback(static function (array $data): bool {
                static::assertSame('Recovery', $data['subject']);
                static::assertSame(\RuntimeException::class, $data['exceptionClass']);
                static::assertStringNotContainsString('BODY-SECRET', json_encode($data, \JSON_THROW_ON_ERROR));

                return true;
            }),
        );

        $flow = new StorableFlow('customer.recovery.request', $context);
        $flow->setData('sensitive', true);
        $flow->setConfig(['mailTemplateId' => $templateId, 'recipient' => ['type' => 'customer']]);
        $flow->setData(MailAware::MAIL_STRUCT, new MailRecipientStruct(['customer@example.com' => 'Customer']));
        $flow->setData(MailAware::SALES_CHANNEL_ID, TestDefaults::SALES_CHANNEL);

        $this->createAction(logger: $logger, updateMailTemplateType: false)->handleFlow($flow);
    }

    /**
     * @param EntityRepository<MailTemplateCollection>|null $mailTemplateRepository
     * @param EntityRepository<MailTemplateTypeCollection>|null $mailTemplateTypeRepository
     */
    private function createAction(
        ?AbstractMailService $mailService = null,
        ?EntityRepository $mailTemplateRepository = null,
        ?EntityRepository $mailTemplateTypeRepository = null,
        ?Connection $connection = null,
        ?JsonEntityEncoder $encoder = null,
        bool $updateMailTemplateType = true,
        ?LoggerInterface $logger = null,
    ): SendMailAction {
        return new SendMailAction(
            $mailService ?? $this->mailService,
            $mailTemplateRepository ?? $this->mailTemplateRepository,
            $logger ?? $this->logger,
            static::createStub(EventDispatcherInterface::class),
            $mailTemplateTypeRepository ?? $this->mailTemplateTypeRepository,
            $connection ?? static::createStub(Connection::class),
            $encoder ?? static::createStub(JsonEntityEncoder::class),
            static::createStub(DefinitionInstanceRegistry::class),
            $updateMailTemplateType
        );
    }
}

/**
 * @internal
 */
class MailTemplateTypeUpdateProvider
{
    /**
     * @internal
     */
    public function __construct(
        public readonly bool $updateMailTemplateTypeParam,
        public readonly ?string $mailTemplateTypeId,
        public readonly bool $sensitive = false,
    ) {
    }
}
