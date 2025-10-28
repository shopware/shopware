<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Health;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\SystemCheck\BaseCheck;
use Shopware\Core\Framework\SystemCheck\Check\Category;
use Shopware\Core\Framework\SystemCheck\Check\Result;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Shopware\Core\Framework\SystemCheck\SystemChecker;

/**
 * @internal
 */
#[CoversClass(SystemChecker::class)]
class SystemCheckerTest extends TestCase
{
    public function testRunAllChecks(): void
    {
        $checks = [
            $this->createMock(BaseCheck::class),
            $this->createMock(BaseCheck::class),
        ];

        $checker = new SystemChecker($checks);
        $context = SystemCheckExecutionContext::WEB;
        $result = new Result('test', Status::OK, 'test', true, []);
        foreach ($checks as $check) {
            $check->expects($this->once())->method('allowedToRunIn')->with($context)->willReturn(true);
            $check->expects($this->once())->method('category')->willReturn(Category::SYSTEM);
            $check->expects($this->once())->method('run')->willReturn($result);
        }

        $results = $checker->check($context);
        static::assertCount(2, $results);
        foreach ($results as $outputResult) {
            static::assertSame($result, $outputResult);
        }
    }

    public function testDoNotRunCheckThatIsNotAllowed(): void
    {
        $checks = [
            $this->createMock(BaseCheck::class),
            $this->createMock(BaseCheck::class),
        ];

        $checker = new SystemChecker($checks);
        $context = SystemCheckExecutionContext::WEB;
        $resultForRunningTest = new Result('test', Status::OK, 'test', true, []);
        $skippedResult = new Result('test', Status::SKIPPED, 'Check not allowed to run in this execution context: WEB', null, []);
        $checks[0]->expects($this->once())->method('allowedToRunIn')->with($context)->willReturn(false);
        $checks[0]->expects($this->once())->method('name')->willReturn('test');
        $checks[0]->expects($this->never())->method('run');
        $checks[0]->expects($this->never())->method('category');

        $checks[1]->expects($this->once())->method('allowedToRunIn')->with($context)->willReturn(true);
        $checks[1]->expects($this->once())->method('category')->willReturn(Category::SYSTEM);
        $checks[1]->expects($this->once())->method('run')->willReturn($resultForRunningTest);

        $results = $checker->check($context);
        static::assertCount(2, $results);
        static::assertSame($resultForRunningTest, $results[0]);
        static::assertEquals($skippedResult, $results[1]);
    }

    public function testSkipTestsIfAnyCorePriorityCheckFails(): void
    {
        $highPriorityCheck = $this->createMock(BaseCheck::class);
        $lowPriorityCheck = $this->createMock(BaseCheck::class);

        $checker = new SystemChecker([$highPriorityCheck, $lowPriorityCheck]);
        $context = SystemCheckExecutionContext::WEB;
        $result = new Result('test', Status::ERROR, 'test', false, []);
        $skippedResult = new Result('test', Status::SKIPPED, 'Check is not run due to previous failed checks.', null, []);

        $highPriorityCheck->expects($this->once())->method('allowedToRunIn')->with($context)->willReturn(true);
        $highPriorityCheck->expects($this->once())->method('category')->willReturn(Category::SYSTEM);
        $highPriorityCheck->expects($this->once())->method('run')->willReturn($result);

        $lowPriorityCheck->expects($this->once())->method('allowedToRunIn')->with($context)->willReturn(true);
        $lowPriorityCheck->expects($this->once())->method('category')->willReturn(Category::AUXILIARY);
        $lowPriorityCheck->expects($this->once())->method('name')->willReturn('test');
        $lowPriorityCheck->expects($this->never())->method('run');

        $results = $checker->check($context);
        static::assertCount(2, $results);
        static::assertSame($result, $results[0]);
        static::assertEquals($skippedResult, $results[1]);
    }
}
