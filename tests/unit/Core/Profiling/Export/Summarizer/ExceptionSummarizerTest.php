<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Profiling\Export\Summarizer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Export\Summarizer\ExceptionSummarizer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\ExceptionDataCollector;
use Symfony\Component\HttpKernel\DataCollector\TimeDataCollector;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ExceptionSummarizer::class)]
class ExceptionSummarizerTest extends TestCase
{
    public function testSupportsOnlyTheExceptionCollector(): void
    {
        static::assertTrue((new ExceptionSummarizer())->supports(new ExceptionDataCollector()));
        static::assertFalse((new ExceptionSummarizer())->supports(new TimeDataCollector()));
    }

    public function testReturnsNullWithoutException(): void
    {
        $collector = new ExceptionDataCollector();
        $collector->collect(new Request(), new Response());

        static::assertNull((new ExceptionSummarizer())->summarize($collector, null));
    }

    public function testSummarizesTheExceptionAndItsPreviousChain(): void
    {
        $previous = new \RuntimeException('Connection refused', 2002);
        $exception = new NotFoundHttpException('Product not found', $previous);
        $collector = new ExceptionDataCollector();
        $collector->collect(new Request(), new Response(), $exception);

        $summary = (new ExceptionSummarizer())->summarize($collector, null);

        static::assertNotNull($summary);
        static::assertSame(NotFoundHttpException::class, $summary['class']);
        static::assertSame('Product not found', $summary['message']);
        static::assertSame(404, $summary['status']);
        static::assertSame(__FILE__, $summary['file']);
        static::assertSame([['class' => \RuntimeException::class, 'message' => 'Connection refused', 'code' => 2002, 'file' => __FILE__, 'line' => $previous->getLine()]], $summary['previous']);
        static::assertSame(__FILE__ . ':' . $exception->getLine(), $summary['trace'][0]);
        static::assertStringStartsWith(self::class . '->testSummarizesTheExceptionAndItsPreviousChain() ', $summary['trace'][1]);
        static::assertLessThanOrEqual(15, \count($summary['trace']));
    }
}
