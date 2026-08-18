<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Framework\Util\Hasher;
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
        $trace->cacheAccess('base', 'cache-key', cacheMiss: false, taxRuleCount: 2);
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
                'cacheKey' => Hasher::hash('cache-key', 'sha256'),
                'cacheMiss' => true,
                'taxRuleCount' => 3,
            ]);

        $trace = new AtsContextCacheTrace($logger);
        $trace->cacheAccess('base', 'cache-key', cacheMiss: true, taxRuleCount: 3);
    }

    public function testLogsTaxWriteOperationWithoutTaxData(): void
    {
        $this->setEnvVars(['ATS_CACHE_TRACE' => '1']);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('ATS sales channel context cache trace.', [
                'event' => 'context-invalidation',
                'taxWriteOperations' => ['insert'],
            ]);

        $trace = new AtsContextCacheTrace($logger);
        $trace->contextInvalidation(['insert']);
    }

    public function testLogsCacheBuildLifecycleWithHashedCacheKey(): void
    {
        $this->setEnvVars(['ATS_CACHE_TRACE' => '1']);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))
            ->method('error')
            ->willReturnCallback(function (string $message, array $context): void {
                static::assertSame('ATS sales channel context cache trace.', $message);
                static::assertContains($context, [
                    [
                        'event' => 'context-cache-build-started',
                        'factory' => 'base',
                        'cacheKey' => Hasher::hash('cache-key', 'sha256'),
                    ],
                    [
                        'event' => 'context-cache-build-completed',
                        'factory' => 'base',
                        'cacheKey' => Hasher::hash('cache-key', 'sha256'),
                        'taxRuleCount' => 3,
                    ],
                ]);
            });

        $trace = new AtsContextCacheTrace($logger);
        $trace->cacheBuildStarted('base', 'cache-key');
        $trace->cacheBuildCompleted('base', 'cache-key', 3);
    }

    public function testLogsWhenInvalidationPreventsCachingTheFreshValue(): void
    {
        $this->setEnvVars(['ATS_CACHE_TRACE' => '1']);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('ATS sales channel context cache trace.', [
                'event' => 'context-cache-build-not-saved',
                'cacheKey' => Hasher::hash('cache-key', 'sha256'),
            ]);

        $trace = new AtsContextCacheTrace($logger);
        $trace->cacheBuildNotSaved('cache-key');
    }
}
