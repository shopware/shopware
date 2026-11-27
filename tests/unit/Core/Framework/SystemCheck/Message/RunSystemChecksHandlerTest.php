<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\SystemCheck\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Shopware\Core\Framework\SystemCheck\Message\RunSystemChecks;
use Shopware\Core\Framework\SystemCheck\Message\RunSystemChecksHandler;
use Shopware\Core\Framework\SystemCheck\SystemChecker;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RunSystemChecksHandler::class)]
class RunSystemChecksHandlerTest extends TestCase
{
    public function testRunsTheRequestedCheckInEveryContext(): void
    {
        $systemChecker = $this->createMock(SystemChecker::class);
        $systemChecker->expects($this->once())->method('check')->with(SystemCheckExecutionContext::cases(), 'AppUrlVerification');

        (new RunSystemChecksHandler($systemChecker))(new RunSystemChecks('AppUrlVerification'));
    }
}
