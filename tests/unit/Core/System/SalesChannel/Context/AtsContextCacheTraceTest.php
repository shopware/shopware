<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\System\SalesChannel\Context\AtsContextCacheTrace;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AtsContextCacheTrace::class)]
class AtsContextCacheTraceTest extends TestCase
{
    use EnvTestBehaviour;

    public function testDoesNotLogWhenTraceIsDisabled(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $trace = new AtsContextCacheTrace($logger);
        $trace->cacheAccess('base', cacheMiss: false, taxRuleCount: 2);
    }

    public function testLogsSanitizedContextCacheAccessWhenTraceIsEnabled(): void
    {
        $this->setEnvVars(['ATS_CACHE_TRACE' => '1']);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('ATS sales channel context cache trace.', [
                'event' => 'context-cache-access',
                'factory' => 'base',
                'cacheMiss' => true,
                'taxRuleCount' => 3,
            ]);

        $trace = new AtsContextCacheTrace($logger);
        $trace->cacheAccess('base', cacheMiss: true, taxRuleCount: 3);
    }
}
