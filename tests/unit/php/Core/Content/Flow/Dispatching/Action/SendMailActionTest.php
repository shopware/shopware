<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Action;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Document\DocumentService;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Content\MailTemplate\Exception\MailEventConfigurationException;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 * @covers \Shopware\Core\Content\Flow\Dispatching\Action\SendMailAction
 */
class SendMailActionTest extends TestCase
{
    private MailTemplateEntity $mailTemplate;

    /**
     * @var MockObject|AbstractMailService
     */
    private $mailService;

    /**
     * @var MockObject|EntityRepository
     */
    private $mailTemplateRepository;

    /**
     * @var MockObject|LanguageLocaleCodeProvider
     */
    private $languageLocaleProvider;

    /**
     * @var MockObject|Translator
     */
    private $translator;

    /**
     * @var MockObject|EntitySearchResult
     */
    private $entitySearchResult;

    private SendMailAction $action;

    /**
     * @var MockObject|StorableFlow
     */
    private $flow;

    public function setUp(): void
    {
        $this->mailTemplate = new MailTemplateEntity();
        $this->mailService = $this->createMock(AbstractMailService::class);
        $this->mailTemplateRepository = $this->createMock(EntityRepository::class);
        $documentGenerator = $this->getMockBuilder(DocumentGenerator::class)->disableOriginalConstructor()->onlyMethods(['generate'])->getMock();
        $this->languageLocaleProvider = $this->createMock(LanguageLocaleCodeProvider::class);
        $this->translator = $this->createMock(Translator::class);
        $this->entitySearchResult = $this->createMock(EntitySearchResult::class);

        $this->action = new SendMailAction(
            $this->mailService,
            $this->mailTemplateRepository,
            $this->createMock(MediaService::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(DocumentService::class),
            $documentGenerator,
            $this->createMock(LoggerInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(EntityRepository::class),
            $this->translator,
            $this->createMock(Connection::class),
            $this->languageLocaleProvider,
            true
        );

        $this->flow = $this->createMock(StorableFlow::class);
    }

    public function testRequirements(): void
    {
        static::assertSame(
            [MailAware::class],
            $this->action->requirements()
        );
    }

    public function testSubscribedEvents(): void
    {
        if (Feature::isActive('v6.5.0.0')) {
            static::assertSame(
                [],
                SendMailAction::getSubscribedEvents()
            );

            return;
        }

        static::assertSame(
            ['action.mail.send' => 'handle'],
            SendMailAction::getSubscribedEvents()
        );
    }

    public function testName(): void
    {
        static::assertSame('action.mail.send', SendMailAction::getName());
    }

    public function testActionExecuted(): void
    {
        $mailTemplateId = Uuid::randomHex();
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
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'templateId' => $mailTemplateId,
                'customFields' => null,
                'contentHtml' => null,
                'contentPlain' => null,
                'subject' => null,
                'mediaIds' => [],
                'senderName' => null,
            ],
            'context' => Context::createDefaultContext(),
        ];

        $templateData = new MailRecipientStruct($expected['data']['recipients']);
        $this->mailTemplate->setId($mailTemplateId);

        $this->flow->expects(static::exactly(2))
            ->method('hasStore')
            ->willReturn(true);

        $this->flow->expects(static::exactly(5))
            ->method('getStore')
            ->willReturnOnConsecutiveCalls(
                Defaults::SALES_CHANNEL,
                ['recipients' => [
                    'email' => 'firstName lastName',
                ]],
                [],
                Defaults::SALES_CHANNEL,
                Uuid::randomHex(),
            );

        $this->flow->expects(static::exactly(2))
            ->method('data')
            ->willReturn(['mailStruct' => $templateData]);

        $this->flow->expects(static::once())
            ->method('getConfig')
            ->willReturn($config);

        $this->flow->expects(static::exactly(6))
            ->method('getContext')
            ->willReturn(Context::createDefaultContext());

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
            ->with($expected['data'], $expected['context'], ['mailStruct' => $templateData]);

        $this->action->handleFlow($this->flow);
    }

    public function testActionWithNotAware(): void
    {
        $this->flow->expects(static::once())->method('hasStore')->willReturn(false);
        $this->flow->expects(static::never())->method('getStore');

        static::expectException(MailEventConfigurationException::class);
        $this->mailService->expects(static::never())->method('send');

        $this->action->handleFlow($this->flow);
    }

    public function testActionWithEmptyConfig(): void
    {
        $this->flow->expects(static::exactly(2))->method('hasStore')->willReturn(true);
        $this->flow->expects(static::never())->method('getStore');
        $this->flow->expects(static::once())->method('getConfig')->willReturn([]);

        static::expectException(MailEventConfigurationException::class);
        $this->mailService->expects(static::never())->method('send');

        $this->action->handleFlow($this->flow);
    }
}
