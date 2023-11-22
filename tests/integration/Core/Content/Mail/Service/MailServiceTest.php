<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Mail\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Mail\Service\AbstractMailSender;
use Shopware\Core\Content\Mail\Service\MailFactory;
use Shopware\Core\Content\Mail\Service\MailService;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Mime\Email;
use Twig\Environment;

/**
 * @internal
 */
class MailServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    public function testPluginsCanExtendMailData(): void
    {
        $renderer = clone $this->getContainer()->get(StringTemplateRenderer::class);
        $property = ReflectionHelper::getProperty(StringTemplateRenderer::class, 'twig');

        $twig = $property->getValue($renderer);
        \assert($twig instanceof Environment);
        $environment = new TestEnvironment($twig->getLoader());
        $property->setValue($renderer, $environment);

        $mailService = new MailService(
            $this->createMock(DataValidator::class),
            $renderer,
            $this->getContainer()->get(MailFactory::class),
            $this->createMock(AbstractMailSender::class),
            $this->createMock(EntityRepository::class),
            $this->getContainer()->get(SalesChannelDefinition::class),
            $this->getContainer()->get('sales_channel.repository'),
            $this->getContainer()->get(SystemConfigService::class),
            $this->getContainer()->get('event_dispatcher'),
            $this->createMock(LoggerInterface::class)
        );
        $data = [
            'senderName' => 'Foo & Bar',
            'recipients' => ['baz@example.com' => 'Baz'],
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'contentHtml' => '<h1>Test</h1>',
            'contentPlain' => 'Test',
            'subject' => 'Test subject & content',
        ];

        $this->addEventListener(
            $this->getContainer()->get('event_dispatcher'),
            MailBeforeValidateEvent::class,
            function (MailBeforeValidateEvent $event): void {
                $event->setTemplateData(
                    [...$event->getTemplateData(), ...['plugin-value' => true]]
                );
            }
        );

        $mailService->send($data, Context::createDefaultContext());

        static::assertArrayHasKey(0, $environment->getCalls());
        $first = $environment->getCalls()[0];
        static::assertArrayHasKey('data', $first);
        static::assertArrayHasKey('plugin-value', $first['data']);
    }

    /**
     * @return array<int, mixed[]>
     */
    public static function senderEmailDataProvider(): array
    {
        return [
            ['basic@example.com', 'basic@example.com', null, null],
            ['config@example.com', null, 'config@example.com', null],
            ['basic@example.com', 'basic@example.com', 'config@example.com', null],
            ['data@example.com', 'basic@example.com', 'config@example.com', 'data@example.com'],
            ['data@example.com', 'basic@example.com', null, 'data@example.com'],
            ['data@example.com', null, 'config@example.com', 'data@example.com'],
        ];
    }

    #[DataProvider('senderEmailDataProvider')]
    public function testEmailSender(string $expected, ?string $basicInformationEmail = null, ?string $configSender = null, ?string $dataSenderEmail = null): void
    {
        $this->getContainer()
            ->get(Connection::class)
            ->executeStatement('DELETE FROM system_config WHERE configuration_key  IN ("core.mailerSettings.senderAddress", "core.basicInformation.email")');

        $systemConfig = $this->getContainer()->get(SystemConfigService::class);
        if ($configSender !== null) {
            $systemConfig->set('core.mailerSettings.senderAddress', $configSender);
        }
        if ($basicInformationEmail !== null) {
            $systemConfig->set('core.basicInformation.email', $basicInformationEmail);
        }

        $mailSender = $this->createMock(AbstractMailSender::class);
        $mailService = new MailService(
            $this->createMock(DataValidator::class),
            $this->getContainer()->get(StringTemplateRenderer::class),
            $this->getContainer()->get(MailFactory::class),
            $mailSender,
            $this->createMock(EntityRepository::class),
            $this->getContainer()->get(SalesChannelDefinition::class),
            $this->getContainer()->get('sales_channel.repository'),
            $systemConfig,
            $this->createMock(EventDispatcher::class),
            $this->createMock(LoggerInterface::class)
        );

        $salesChannel = $this->createSalesChannel();

        $data = [
            'senderName' => 'Foo & Bar',
            'recipients' => ['baz@example.com' => 'Baz'],
            'salesChannelId' => $salesChannel['id'],
            'contentHtml' => '<h1>Test</h1>',
            'contentPlain' => 'Test',
            'subject' => 'Test subject & content',
        ];
        if ($dataSenderEmail !== null) {
            $data['senderMail'] = $dataSenderEmail;
        }

        $mailSender->expects(static::once())
            ->method('send')
            ->with(static::callback(function (Email $mail) use ($expected, $data): bool {
                $from = $mail->getFrom();
                $this->assertSame($data['senderName'], $from[0]->getName());
                $this->assertSame($data['subject'], $mail->getSubject());
                $this->assertCount(1, $from);
                $this->assertSame($data['senderMail'] ?? $expected, $from[0]->getAddress());

                return true;
            }));
        $mailService->send($data, Context::createDefaultContext());
    }

    public function testItAllowsManipulationOfDataInBeforeValidateEvent(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(MailBeforeValidateEvent::class, static function (MailBeforeValidateEvent $event): void {
            $data = $event->getData();
            $data['senderEmail'] = 'test@email.com';

            $event->setData($data);
        });
        $mailSender = $this->createMock(AbstractMailSender::class);
        $mailService = new MailService(
            $this->createMock(DataValidator::class),
            $this->createMock(StringTemplateRenderer::class),
            $this->getContainer()->get(MailFactory::class),
            $mailSender,
            $this->createMock(EntityRepository::class),
            $this->getContainer()->get(SalesChannelDefinition::class),
            $this->getContainer()->get('sales_channel.repository'),
            $this->getContainer()->get(SystemConfigService::class),
            $eventDispatcher,
            $this->createMock(LoggerInterface::class)
        );

        $salesChannel = $this->createSalesChannel();

        $data = [
            'senderName' => 'Foo Bar',
            'recipients' => ['baz@example.com' => 'Baz'],
            'salesChannelId' => $salesChannel['id'],
            'contentHtml' => '<h1>Test</h1>',
            'contentPlain' => 'Test',
            'subject' => 'Test subject',
        ];

        $mailSender->expects(static::once())
            ->method('send')
            ->with(static::callback(function (Email $mail): bool {
                $from = $mail->getFrom();
                $this->assertCount(1, $from);
                $this->assertSame('test@email.com', $from[0]->getAddress());

                return true;
            }));
        $mailService->send($data, Context::createDefaultContext());
    }

    public function testMailSendingInTestMode(): void
    {
        $mailSender = $this->createMock(AbstractMailSender::class);
        $templateRenderer = $this->createMock(StringTemplateRenderer::class);
        $mailService = new MailService(
            $this->createMock(DataValidator::class),
            $templateRenderer,
            $this->getContainer()->get(MailFactory::class),
            $mailSender,
            $this->createMock(EntityRepository::class),
            $this->getContainer()->get(SalesChannelDefinition::class),
            $this->getContainer()->get('sales_channel.repository'),
            $this->getContainer()->get(SystemConfigService::class),
            $this->createMock(EventDispatcher::class),
            $this->createMock(LoggerInterface::class)
        );

        $salesChannel = $this->createSalesChannel();

        $data = [
            'senderName' => 'Foo Bar',
            'recipients' => ['baz@example.com' => 'Baz'],
            'salesChannelId' => $salesChannel['id'],
            'contentHtml' => '<span>Test</span>',
            'contentPlain' => 'Test',
            'subject' => 'Test subject',
            'testMode' => true,
        ];

        $templateData = [
            'salesChannel' => [],
            'order' => [
                'deepLinkCode' => 'home',
            ],
        ];

        $mailSender->expects(static::once())
            ->method('send')
            ->with(static::callback(function (Email $mail): bool {
                $from = $mail->getFrom();
                $this->assertCount(1, $from);

                return true;
            }));
        $mailService->send($data, Context::createDefaultContext(), $templateData);
    }

    public function testHtmlEscaping(): void
    {
        $mailSender = $this->createMock(AbstractMailSender::class);
        $mailService = new MailService(
            $this->createMock(DataValidator::class),
            $this->getContainer()->get(StringTemplateRenderer::class),
            $this->getContainer()->get(MailFactory::class),
            $mailSender,
            $this->createMock(EntityRepository::class),
            $this->getContainer()->get(SalesChannelDefinition::class),
            $this->getContainer()->get('sales_channel.repository'),
            $this->getContainer()->get(SystemConfigService::class),
            $this->createMock(EventDispatcher::class),
            $this->createMock(LoggerInterface::class)
        );

        $salesChannel = $this->createSalesChannel();

        $data = [
            'senderName' => 'Foo & Bar',
            'recipients' => ['baz@example.com' => 'Baz'],
            'salesChannelId' => $salesChannel['id'],
            'contentHtml' => '<a href="{{ url }}">{{ text }}</a>',
            'contentPlain' => '{{ text }} {{ url }}',
            'subject' => 'Test',
            'senderEmail' => 'test@example.com',
        ];

        $mail = $mailService->send($data, Context::createDefaultContext(), [
            'text' => '<foobar>',
            'url' => 'http://example.com/?foo&bar=baz',
        ]);

        static::assertInstanceOf(Email::class, $mail);
        static::assertEquals('<a href="http://example.com/?foo&amp;bar=baz">&lt;foobar&gt;</a>', $mail->getHtmlBody());
        static::assertEquals('<foobar> http://example.com/?foo&bar=baz', $mail->getTextBody());
    }
}

/**
 * @internal
 */
class TestEnvironment extends Environment
{
    /**
     * @var array<int, mixed[]>
     */
    private array $calls = [];

    /**
     * @param mixed[] $context
     */
    public function render($name, array $context = []): string
    {
        $this->calls[] = ['source' => $name, 'data' => $context];

        return parent::render($name, $context);
    }

    /**
     * @return array<int, mixed[]>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }
}
