<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Health;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Health\ErrorClassification;
use Shopware\Core\Framework\Webhook\Health\HttpErrorClassifier;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(HttpErrorClassifier::class)]
class HttpErrorClassifierTest extends TestCase
{
    #[DataProvider('statusProvider')]
    public function testClassifiesStatusCodes(int $statusCode, ErrorClassification $expected): void
    {
        static::assertSame($expected, (new HttpErrorClassifier())->classify($statusCode));
    }

    /**
     * @return iterable<string, array{int, ErrorClassification}>
     */
    public static function statusProvider(): iterable
    {
        yield 'no response' => [0, ErrorClassification::TransientNetwork];
        yield 'lower success boundary' => [200, ErrorClassification::Success];
        yield 'upper success boundary' => [299, ErrorClassification::Success];
        yield 'lower redirect boundary' => [300, ErrorClassification::TransientRedirect];
        yield 'upper redirect boundary' => [399, ErrorClassification::TransientRedirect];
        yield 'payload rejection' => [400, ErrorClassification::NonTransientPayload];
        yield 'unauthorized' => [401, ErrorClassification::NonTransientAuth];
        yield 'forbidden' => [403, ErrorClassification::NonTransientAuth];
        yield 'not found' => [404, ErrorClassification::TransientServer];
        yield 'request timeout' => [408, ErrorClassification::TransientServer];
        yield 'gone' => [410, ErrorClassification::NonTransientEndpoint];
        yield 'rate limit' => [429, ErrorClassification::TransientRateLimit];
        yield 'unlisted client error' => [451, ErrorClassification::NonTransientPayload];
        yield 'lower server error boundary' => [500, ErrorClassification::TransientServer];
        yield 'upper server error boundary' => [599, ErrorClassification::TransientServer];
    }
}
