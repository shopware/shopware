<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\MailTemplate\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\MailTemplate\Service\MailSenderInterface;
use Shopware\Core\Content\MailTemplate\Service\MailService;
use Shopware\Core\Content\MailTemplate\Service\MessageFactory;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcher;

class MailServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    public function senderEmailDataProvider(): array
    {
        return [
            ['basic@example', 'basic@example', null, null],
            ['config@example', null, 'config@example', null],
            ['basic@example', 'basic@example', 'config@example', null],
            ['data@example', 'basic@example', 'config@example', 'data@example'],
            ['data@example', 'basic@example', null, 'data@example'],
            ['data@example', null, 'config@example', 'data@example'],
        ];
    }

    /**
     * @dataProvider senderEmailDataProvider
     */
    public function testSenderEmail(string $expected, ?string $basicInformationEmail = null, ?string $configSender = null, ?string $dataSenderEmail = null): void
    {
        $this->getContainer()
            ->get(Connection::class)
            ->executeUpdate('DELETE FROM system_config WHERE configuration_key  IN ("core.mailerSettings.senderAddress", "core.basicInformation.email")');

        $systemConfig = $this->getContainer()->get(SystemConfigService::class);
        if ($configSender !== null) {
            $systemConfig->set('core.mailerSettings.senderAddress', $configSender);
        }
        if ($basicInformationEmail !== null) {
            $systemConfig->set('core.basicInformation.email', $basicInformationEmail);
        }

        $mailSender = $this->createMock(MailSenderInterface::class);
        $salesChannelRepository = $this->getContainer()->get('sales_channel.repository');
        $mailService = new MailService(
            $this->createMock(DataValidator::class),
            $this->createMock(StringTemplateRenderer::class),
            $this->getContainer()->get(MessageFactory::class),
            $mailSender,
            $this->createMock(EntityRepositoryInterface::class),
            $salesChannelRepository->getDefinition(),
            $salesChannelRepository,
            $systemConfig,
            $this->createMock(EventDispatcher::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(UrlGeneratorInterface::class)
        );

        $salesChannel = $this->createSalesChannel();

        $data = [
            'senderName' => 'Foo Bar',
            'recipients' => ['baz@example' => 'Baz'],
            'salesChannelId' => $salesChannel['id'],
            'contentHtml' => '<h1>Test</h1>',
            'contentPlain' => 'Test',
            'subject' => 'Test subject',
        ];
        if ($dataSenderEmail !== null) {
            $data['senderEmail'] = $dataSenderEmail;
        }

        $mailSender->expects(static::once())
            ->method('send')
            ->with(static::callback(function (\Swift_Message $message) use ($expected): bool {
                $from = array_keys($message->getFrom());
                $this->assertCount(1, $from);
                $this->assertSame($expected, $from[0]);

                return true;
            }));
        $mailService->send($data, Context::createDefaultContext());
    }
}
