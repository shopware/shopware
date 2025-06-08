<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\Staging\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\Service\MailSender;
use Shopware\Core\Framework\Context;
use Shopware\Core\Maintenance\Staging\Event\SetupStagingEvent;
use Shopware\Core\Maintenance\Staging\Handler\StagingMailHandler;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[CoversClass(StagingMailHandler::class)]
class StagingMailHandlerTest extends TestCase
{
    public function testDisabled(): void
    {
        $config = new StaticSystemConfigService();
        $handler = new StagingMailHandler(false, $config);

        $handler(new SetupStagingEvent(Context::createDefaultContext(), $this->createMock(SymfonyStyle::class)));

        static::assertNull($config->get(MailSender::DISABLE_MAIL_DELIVERY));
    }

    public function testEnabled(): void
    {
        $config = new StaticSystemConfigService();
        $handler = new StagingMailHandler(true, $config);

        $handler(new SetupStagingEvent(Context::createDefaultContext(), $this->createMock(SymfonyStyle::class)));

        static::assertTrue($config->get(MailSender::DISABLE_MAIL_DELIVERY));
    }
}
