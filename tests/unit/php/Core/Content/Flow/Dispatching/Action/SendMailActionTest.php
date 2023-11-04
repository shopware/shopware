<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Action;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Flow\Dispatching\Action\FlowMailVariables;
use Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Content\Mail\Service\MailAttachmentsConfig;
use Shopware\Core\Content\MailTemplate\Exception\MailEventConfigurationException;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\Test\TestDefaults;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @package business-ops
 *
 * @internal
 *
 * @covers \Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction
 */
class SendMailActionTest extends TestCase
{
    private MailTemplateEntity $mailTemplate;

    /**
     * @var AbstractMailService&MockObject
     */
    private AbstractMailService $mailService;

    /**
     * @var EntityRepository&MockObject
     */
    private EntityRepository $mailTemplateRepository;

    /**
     * @var LanguageLocaleCodeProvider&MockObject
     */
    private LanguageLocaleCodeProvider $languageLocaleProvider;

    /**
     * @var Translator&MockObject
     */
    private Translator $translator;

    /**
     * @var EntitySearchResult&MockObject
     */
    private EntitySearchResult $entitySearchResult;

    private SendMailAction $action;

    protected function setUp(): void
    {
        $this->mailTemplate = new MailTemplateEntity();
        $this->mailService = $this->createMock(AbstractMailService::class);
        $this->mailTemplateRepository = $this->createMock(EntityRepository::class);
        $this->languageLocaleProvider = $this->createMock(LanguageLocaleCodeProvider::class);
        $this->translator = $this->createMock(Translator::class);
        $this->entitySearchResult = $this->createMock(EntitySearchResult::class);

        $this->action = new SendMailAction(
            $this->mailService,
            $this->mailTemplateRepository,
            $this->createMock(LoggerInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(EntityRepository::class),
            $this->translator,
            $this->createMock(Connection::class),
            $this->languageLocaleProvider,
            true
        );
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
     * @dataProvider replyToProvider
     *
     * @param array<string, string> $exptectedReplyTo
     */
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

        $this->entitySearchResult->expects(static::once())
            ->method('first')
            ->willReturn($this->mailTemplate);

        $this->mailTemplateRepository->expects(static::once())
            ->method('search')
            ->willReturn($this->entitySearchResult);

        $this->translator->expects(static::once())
            ->method('getSnippetSetId')
            ->willReturn(null);

        $this->languageLocaleProvider->expects(static::once())
            ->method('getLocaleForLanguageId')
            ->willReturn('en-GB');

        $this->mailService->expects(static::once())
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

        $this->action->handleFlow($flow);
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
        $this->mailService->expects(static::never())->method('send');

        $this->action->handleFlow($flow);
    }

    public function testActionWithEmptyConfig(): void
    {
        $flow = new StorableFlow('', Context::createDefaultContext(), []);

        static::expectException(MailEventConfigurationException::class);
        $this->mailService->expects(static::never())->method('send');

        static::expectException(MailEventConfigurationException::class);
        $this->mailService->expects(static::never())->method('send');

        $this->action->handleFlow($flow);
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
        $flow->setData(FlowMailVariables::CONTACT_FORM_DATA, [
            'email' => 'customer@example.com',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
        ]);

        $flow->setConfig($config);

        $this->entitySearchResult->expects(static::once())
            ->method('first')
            ->willReturn($this->mailTemplate);

        $this->mailTemplateRepository->expects(static::once())
            ->method('search')
            ->willReturn($this->entitySearchResult);

        $this->translator->expects(static::once())
            ->method('getSnippetSetId')
            ->willReturn(null);

        $this->languageLocaleProvider->expects(static::once())
            ->method('getLocaleForLanguageId')
            ->willReturn('en-GB');

        $this->mailService->expects(static::once())
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
                ]
            );

        $this->action->handleFlow($flow);
    }
}
