<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Mail\Service;

use Monolog\Level;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Mail\Service\AbstractMailFactory;
use Shopware\Core\Content\Mail\Service\AbstractMailSender;
use Shopware\Core\Content\Mail\Service\MailService;
use Shopware\Core\Content\Mail\Telemetry\MailMetricsInstrumentor;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailErrorEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailTemplateRenderContextEvent;
use Shopware\Core\Content\MailTemplate\Service\MailTemplateContentBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\HeaderInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(MailService::class)]
class MailServiceTest extends TestCase
{
    /**
     * @var Stub&StringTemplateRenderer
     */
    private StringTemplateRenderer $templateRenderer;

    /**
     * @var MockObject&AbstractMailFactory
     */
    private AbstractMailFactory $mailFactory;

    /**
     * @var Stub&EventDispatcherInterface
     */
    private EventDispatcherInterface $eventDispatcher;

    /**
     * @var MockObject&EntityRepository<SalesChannelCollection>
     */
    private EntityRepository $salesChannelRepository;

    /**
     * @var Stub&LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var Stub&AbstractMailSender
     */
    private AbstractMailSender $mailSender;

    /**
     * @var Stub&LanguageLocaleCodeProvider
     */
    private LanguageLocaleCodeProvider $languageLocaleCodeProvider;

    protected function setUp(): void
    {
        $this->mailFactory = $this->createMock(AbstractMailFactory::class);
        $this->eventDispatcher = static::createStub(EventDispatcherInterface::class);
        $this->templateRenderer = static::createStub(StringTemplateRenderer::class);
        $this->salesChannelRepository = $this->createMock(EntityRepository::class);
        $this->logger = static::createStub(LoggerInterface::class);
        $this->mailSender = static::createStub(AbstractMailSender::class);
        $this->languageLocaleCodeProvider = static::createStub(LanguageLocaleCodeProvider::class);
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

        $this->salesChannelRepository->expects($this->once())->method('search')->willReturn($salesChannelResult);

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

        $this->mailFactory->expects($this->once())->method('create')->willReturn($email);
        $templateRenderer = $this->createMock(StringTemplateRenderer::class);
        $templateRenderer->expects($this->exactly(4))->method('render')->willReturn('');
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(4))->method('dispatch')->willReturnOnConsecutiveCalls(
            static::isInstanceOf(MailBeforeValidateEvent::class),
            static::isInstanceOf(MailTemplateRenderContextEvent::class),
            static::isInstanceOf(MailBeforeSentEvent::class),
            static::isInstanceOf(MailSentEvent::class)
        );
        $email = $this->createMailService(
            templateRenderer: $templateRenderer,
            eventDispatcher: $eventDispatcher
        )->send($data, Context::createDefaultContext());

        static::assertInstanceOf(Email::class, $email);
    }

    public function testSendMailDispatchesMailSentEventWithRenderedSubject(): void
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

        $this->salesChannelRepository->expects($this->once())->method('search')->willReturn($salesChannelResult);

        $data = [
            'recipients' => [],
            'senderName' => 'me',
            'senderEmail' => 'me@shopware.com',
            'subject' => 'Your order {{ order.orderNumber }}',
            'contentPlain' => 'Content plain',
            'contentHtml' => 'Content html',
            'salesChannelId' => $salesChannelId,
        ];

        $email = (new Email())->subject('Your order 10001')
            ->html($data['contentHtml'])
            ->text($data['contentPlain'])
            ->to('me@shopware.com')
            ->from(new Address($data['senderEmail']));

        $this->mailFactory->expects($this->once())->method('create')->willReturn($email);

        $templateRenderer = $this->createMock(StringTemplateRenderer::class);
        $templateRenderer->expects($this->exactly(4))->method('render')->willReturnCallback(
            static fn (string $template) => $template === 'Your order {{ order.orderNumber }}' ? 'Your order 10001' : $template
        );

        $mailSentEvent = null;
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(4))
            ->method('dispatch')
            ->willReturnCallback(static function (ShopwareEvent $event) use (&$mailSentEvent) {
                if ($event instanceof MailSentEvent) {
                    $mailSentEvent = $event;
                }

                return $event;
            });

        $this->createMailService(
            templateRenderer: $templateRenderer,
            eventDispatcher: $eventDispatcher
        )->send($data, Context::createDefaultContext(), ['order' => ['orderNumber' => '10001']]);

        static::assertInstanceOf(MailSentEvent::class, $mailSentEvent);
        static::assertSame('Your order 10001', $mailSentEvent->getSubject());
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

        $this->salesChannelRepository->expects($this->once())->method('search')->willReturn($salesChannelResult);

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

        $this->mailFactory->expects($this->never())->method('create')->willReturn($email);
        $beforeValidateEvent = null;
        $mailErrorEvent = null;

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('log')->with(Level::Warning);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(3))
            ->method('dispatch')
            ->willReturnCallback(static function (ShopwareEvent $event) use (&$beforeValidateEvent, &$mailErrorEvent) {
                if ($event instanceof MailBeforeValidateEvent) {
                    $beforeValidateEvent = $event;

                    return $event;
                }

                if ($event instanceof MailTemplateRenderContextEvent) {
                    return $event;
                }

                $mailErrorEvent = $event;

                return $event;
            });

        $templateRenderer = $this->createMock(StringTemplateRenderer::class);
        $templateRenderer->expects($this->once())->method('render')->willThrowException(new \Exception('cannot render'));

        $email = $this->createMailService(
            templateRenderer: $templateRenderer,
            eventDispatcher: $eventDispatcher,
            logger: $logger
        )->send($data, Context::createDefaultContext());

        static::assertNull($email);
        static::assertNotNull($beforeValidateEvent);
        static::assertInstanceOf(MailErrorEvent::class, $mailErrorEvent);
        static::assertSame(Level::Warning, $mailErrorEvent->getLogLevel());
        static::assertNotNull($mailErrorEvent->getMessage());

        $message = 'Could not render Mail-Subject with error message: cannot render';

        static::assertSame($message, $mailErrorEvent->getMessage());
        static::assertSame('Test email', $mailErrorEvent->getTemplate());
        static::assertSame([
            'salesChannel' => $salesChannel,
            'salesChannelId' => $salesChannelId,
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

        $this->salesChannelRepository->expects($this->once())->method('search')->willReturn($salesChannelResult);

        $data = [
            'recipients' => [],
            'subject' => 'Test email',
            'senderName' => null,
            'contentPlain' => 'Content plain',
            'contentHtml' => 'Content html',
            'salesChannelId' => $salesChannelId,
        ];

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('log')->with(Level::Error);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(5))->method('dispatch')->willReturnOnConsecutiveCalls(
            static::isInstanceOf(MailBeforeValidateEvent::class),
            static::isInstanceOf(MailTemplateRenderContextEvent::class),
            static::isInstanceOf(MailErrorEvent::class),
            static::isInstanceOf(MailBeforeSentEvent::class),
            static::isInstanceOf(MailSentEvent::class)
        );

        $email = (new Email())->subject($data['subject'])
            ->html($data['contentHtml'])
            ->text($data['contentPlain'])
            ->to('test@shopware.com')
            ->from(new Address('test@shopware.com'));

        $this->mailFactory->expects($this->once())->method('create')->willReturn($email);

        $email = $this->createMailService(
            eventDispatcher: $eventDispatcher,
            logger: $logger
        )->send($data, Context::createDefaultContext());

        static::assertInstanceOf(Email::class, $email);
    }

    public function testMailSenderExceptionIsHandled(): void
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

        $this->salesChannelRepository->expects($this->once())->method('search')->willReturn($salesChannelResult);

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

        $this->mailFactory->expects($this->once())->method('create')->willReturn($email);
        $templateRenderer = $this->createMock(StringTemplateRenderer::class);
        $templateRenderer->expects($this->exactly(4))->method('render')->willReturn('');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('log')->with(Level::Error);

        $beforeValidateEvent = null;
        $mailErrorEvent = null;

        $this->eventDispatcher
            ->method('dispatch')
            ->willReturnCallback(static function (ShopwareEvent $event) use (&$beforeValidateEvent, &$mailErrorEvent) {
                if ($event instanceof MailBeforeValidateEvent) {
                    $beforeValidateEvent = $event;

                    return $event;
                }

                if ($event instanceof MailErrorEvent) {
                    $mailErrorEvent = $event;
                }

                return $event;
            });

        $mailSender = $this->createMock(AbstractMailSender::class);
        $mailSender->expects($this->once())->method('send')->willThrowException(new \Exception('Mail sending failed'));

        $email = $this->createMailService(
            templateRenderer: $templateRenderer,
            mailSender: $mailSender,
            logger: $logger
        )->send($data, Context::createDefaultContext());

        static::assertNull($email);
        static::assertNotNull($beforeValidateEvent);
        static::assertInstanceOf(MailErrorEvent::class, $mailErrorEvent);
        static::assertSame(Level::Error, $mailErrorEvent->getLogLevel());
        static::assertNotNull($mailErrorEvent->getMessage());
        static::assertSame('Could not send mail with error message: Mail sending failed', $mailErrorEvent->getMessage());
        static::assertSame('Content html', $mailErrorEvent->getTemplate());
        static::assertEmpty($mailErrorEvent->getTemplateData());
    }

    public function testMailInTestModeHasNoEmptyHeaders(): void
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

        $this->salesChannelRepository->expects($this->once())->method('search')->willReturn($salesChannelResult);

        $data = [
            'testMode' => true,
            'recipients' => [],
            'senderName' => 'me',
            'senderEmail' => 'me@shopware.com',
            'subject' => 'Test email',
            'contentPlain' => 'Content plain',
            'contentHtml' => 'Content html',
            'salesChannelId' => $salesChannelId,
        ];

        $templateData = [
            'eventName' => 'checkout.order.placed',
        ];

        $email = (new Email())->subject($data['subject'])
            ->html($data['contentHtml'])
            ->text($data['contentPlain'])
            ->to('me@shopware.com')
            ->from(new Address($data['senderEmail']));

        $this->mailFactory->expects($this->once())->method('create')->willReturn($email);
        $templateRenderer = $this->createMock(StringTemplateRenderer::class);
        $templateRenderer->expects($this->exactly(4))->method('render')->willReturn('');
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(4))->method('dispatch')->willReturnOnConsecutiveCalls(
            static::isInstanceOf(MailBeforeValidateEvent::class),
            static::isInstanceOf(MailTemplateRenderContextEvent::class),
            static::isInstanceOf(MailBeforeSentEvent::class),
            static::isInstanceOf(MailSentEvent::class)
        );
        $languageLocaleCodeProvider = $this->createMock(LanguageLocaleCodeProvider::class);
        $languageLocaleCodeProvider->expects($this->once())->method('getLocaleForLanguageId')->willReturn('en-GB');

        $email = $this->createMailService(
            templateRenderer: $templateRenderer,
            eventDispatcher: $eventDispatcher,
            languageLocaleCodeProvider: $languageLocaleCodeProvider
        )->send($data, Context::createDefaultContext(), $templateData);

        static::assertInstanceOf(Email::class, $email);
        $headers = $email->getHeaders();
        static::assertSame(Defaults::LANGUAGE_SYSTEM, $headers->get('X-Shopware-Language-Id')?->getBody());
        static::assertSame($salesChannelId, $headers->get('X-Shopware-Sales-Channel-Id')?->getBody());
        static::assertSame('checkout.order.placed', $headers->get('X-Shopware-Event-Name')?->getBody());

        // check that no header is empty (e.g., Amazon SES doesn't like that)
        foreach ($headers->all() as $header) {
            static::assertInstanceOf(HeaderInterface::class, $header);
            static::assertNotEmpty($header->getBodyAsString(), 'mail header ' . $header->getName() . ' should not be empty');
        }
    }

    private function createMailService(
        ?StringTemplateRenderer $templateRenderer = null,
        ?AbstractMailSender $mailSender = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?LoggerInterface $logger = null,
        ?LanguageLocaleCodeProvider $languageLocaleCodeProvider = null,
    ): MailService {
        $mailMetrics = static::createStub(MailMetricsInstrumentor::class);
        $mailMetrics->method('measureSend')->willReturnCallback(
            static fn (?string $eventName, \Closure $send) => $send()
        );

        return new MailService(
            static::createStub(DataValidator::class),
            $templateRenderer ?? $this->templateRenderer,
            $this->mailFactory,
            $mailSender ?? $this->mailSender,
            static::createStub(EntityRepository::class),
            $this->salesChannelRepository,
            static::createStub(SystemConfigService::class),
            $eventDispatcher ?? $this->eventDispatcher,
            $logger ?? $this->logger,
            $languageLocaleCodeProvider ?? $this->languageLocaleCodeProvider,
            new MailTemplateContentBuilder(),
            $mailMetrics,
        );
    }
}
