<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Mail\Service;

use Monolog\Level;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Mail\Service\AbstractMailFactory;
use Shopware\Core\Content\Mail\Service\AbstractMailSender;
use Shopware\Core\Content\Mail\Service\MailService;
use Shopware\Core\Content\MailTemplate\Exception\SalesChannelNotFoundException;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailErrorEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
#[CoversClass(MailService::class)]
class MailServiceTest extends TestCase
{
    /**
     * @var MockObject&StringTemplateRenderer
     */
    private StringTemplateRenderer $templateRenderer;

    /**
     * @var MockObject&AbstractMailFactory
     */
    private AbstractMailFactory $mailFactory;

    /**
     * @var MockObject&EventDispatcherInterface
     */
    private EventDispatcherInterface $eventDispatcher;

    private MailService $mailService;

    /**
     * @var MockObject&EntityRepository
     */
    private EntityRepository $salesChannelRepository;

    /**
     * @var MockObject&LoggerInterface
     */
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->mailFactory = $this->createMock(AbstractMailFactory::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->templateRenderer = $this->createMock(StringTemplateRenderer::class);
        $this->salesChannelRepository = $this->createMock(EntityRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->mailService = new MailService(
            $this->createMock(DataValidator::class),
            $this->templateRenderer,
            $this->mailFactory,
            $this->createMock(AbstractMailSender::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(SalesChannelDefinition::class),
            $this->salesChannelRepository,
            $this->createMock(SystemConfigService::class),
            $this->eventDispatcher,
            $this->logger,
        );
    }

    public function testThrowSalesChannelNotFound(): void
    {
        $salesChannelId = Uuid::randomHex();
        $exception = new SalesChannelNotFoundException($salesChannelId);
        static::expectExceptionObject($exception);

        $data = [
            'recipients' => [],
            'salesChannelId' => $salesChannelId,
        ];

        $this->mailService->send($data, Context::createDefaultContext());
    }

    public function testSendMailSuccess(): void
    {
        $salesChannelId = Uuid::randomHex();

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);
        $context = Context::createDefaultContext();

        $salesChannelResult = new EntitySearchResult(
            'sales_channel',
            1,
            new SalesChannelCollection([$salesChannel]),
            null,
            new Criteria(),
            $context
        );

        $this->salesChannelRepository->expects(static::once())->method('search')->willReturn($salesChannelResult);

        $data = [
            'recipients' => [],
            'senderName' => 'me',
            'senderEmail' => 'me@shopware.com',
            'subject' => 'Test email',
            'contentPlain' => 'Content plain',
            'contentHtml' => 'Content html',
            'salesChannelId' => $salesChannelId,
        ];

        $email = (new Email())->subject($data['subject'])
            ->html($data['contentHtml'])
            ->text($data['contentPlain'])
            ->to('me@shopware.com')
            ->from(new Address($data['senderEmail']));

        $this->mailFactory->expects(static::once())->method('create')->willReturn($email);
        $this->templateRenderer->expects(static::exactly(4))->method('render')->willReturn('');
        $this->eventDispatcher->expects(static::exactly(3))->method('dispatch')->willReturnOnConsecutiveCalls(
            static::isInstanceOf(MailBeforeValidateEvent::class),
            static::isInstanceOf(MailBeforeSentEvent::class),
            static::isInstanceOf(MailSentEvent::class)
        );
        $email = $this->mailService->send($data, Context::createDefaultContext());

        static::assertInstanceOf(Email::class, $email);
    }

    public function testSendMailWithRenderingError(): void
    {
        $salesChannelId = Uuid::randomHex();

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);
        $context = Context::createDefaultContext();

        $salesChannelResult = new EntitySearchResult(
            'sales_channel',
            1,
            new SalesChannelCollection([$salesChannel]),
            null,
            new Criteria(),
            $context
        );

        $this->salesChannelRepository->expects(static::once())->method('search')->willReturn($salesChannelResult);

        $data = [
            'recipients' => [],
            'senderName' => 'me',
            'senderEmail' => 'me@shopware.com',
            'subject' => 'Test email',
            'contentPlain' => 'Content plain',
            'contentHtml' => 'Content html',
            'salesChannelId' => $salesChannelId,
        ];

        $email = (new Email())->subject($data['subject'])
            ->html($data['contentHtml'])
            ->text($data['contentPlain'])
            ->to($data['senderEmail'])
            ->from(new Address($data['senderEmail']));

        $this->mailFactory->expects(static::never())->method('create')->willReturn($email);
        $beforeValidateEvent = null;
        $mailErrorEvent = null;

        $this->logger->expects(static::once())->method('warning');
        $this->eventDispatcher->expects(static::exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (Event $event) use (&$beforeValidateEvent, &$mailErrorEvent) {
                if ($event instanceof MailBeforeValidateEvent) {
                    $beforeValidateEvent = $event;

                    return $event;
                }

                $mailErrorEvent = $event;

                return $event;
            });

        $this->templateRenderer->expects(static::exactly(1))->method('render')->willThrowException(new \Exception('cannot render'));

        $email = $this->mailService->send($data, Context::createDefaultContext());

        static::assertNull($email);
        static::assertNotNull($beforeValidateEvent);
        static::assertInstanceOf(MailErrorEvent::class, $mailErrorEvent);
        static::assertEquals(Level::Warning, $mailErrorEvent->getLogLevel());
        static::assertNotNull($mailErrorEvent->getMessage());

        $message = 'Could not render Mail-Template with error message: cannot render';

        static::assertSame($message, $mailErrorEvent->getMessage());
        static::assertSame('Test email', $mailErrorEvent->getTemplate());
        static::assertSame([
            'salesChannel' => $salesChannel,
        ], $mailErrorEvent->getTemplateData());
    }

    public function testSendMailWithoutSenderName(): void
    {
        $salesChannelId = Uuid::randomHex();

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);
        $context = Context::createDefaultContext();

        $salesChannelResult = new EntitySearchResult(
            'sales_channel',
            1,
            new SalesChannelCollection([$salesChannel]),
            null,
            new Criteria(),
            $context
        );

        $this->salesChannelRepository->expects(static::once())->method('search')->willReturn($salesChannelResult);

        $data = [
            'recipients' => [],
            'subject' => 'Test email',
            'senderName' => 'me@shopware.com',
            'contentPlain' => 'Content plain',
            'contentHtml' => 'Content html',
            'salesChannelId' => $salesChannelId,
        ];

        $this->logger->expects(static::once())->method('error');
        $this->eventDispatcher->expects(static::exactly(4))->method('dispatch')->willReturnOnConsecutiveCalls(
            static::isInstanceOf(MailBeforeValidateEvent::class),
            static::isInstanceOf(MailErrorEvent::class),
            static::isInstanceOf(MailBeforeSentEvent::class),
            static::isInstanceOf(MailSentEvent::class)
        );

        $email = (new Email())->subject($data['subject'])
            ->html($data['contentHtml'])
            ->text($data['contentPlain'])
            ->to('test@shopware.com')
            ->from(new Address('test@shopware.com'));

        $this->mailFactory->expects(static::once())->method('create')->willReturn($email);

        $email = $this->mailService->send($data, Context::createDefaultContext());

        static::assertInstanceOf(Email::class, $email);
    }
}
