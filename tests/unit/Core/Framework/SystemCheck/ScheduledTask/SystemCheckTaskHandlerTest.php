<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\SystemCheck\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Shopware\Core\Framework\SystemCheck\ScheduledTask\SystemCheckTaskHandler;
use Shopware\Core\Framework\SystemCheck\SystemChecker;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SystemCheckTaskHandler::class)]
class SystemCheckTaskHandlerTest extends TestCase
{
    public function testRunsTheRecurrentChecks(): void
    {
        $systemChecker = $this->createMock(SystemChecker::class);
        $systemChecker->expects($this->once())->method('check')->with(SystemCheckExecutionContext::RECURRENT);

        $handler = new SystemCheckTaskHandler(static::createStub(EntityRepository::class), new NullLogger(), $systemChecker);
        $handler->run();
    }
}
