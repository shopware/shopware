<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\ContentInlineMappingParseBatchRequest;
use Shopware\Core\Framework\ContentSystem\Api\ContentInlineMappingParseRequest;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentInlineMappingParseBatchRequest::class)]
class ContentInlineMappingParseBatchRequestTest extends TestCase
{
    #[DataProvider('batchSizeProvider')]
    public function testLimitsTheNumberOfRequests(int $count, int $violationCount): void
    {
        $request = new ContentInlineMappingParseBatchRequest(array_fill(0, $count, new ContentInlineMappingParseRequest('Text', 0)));
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        $violations = $validator->validate($request);

        static::assertCount($violationCount, $violations);
        if ($violationCount > 0) {
            static::assertSame('requests', $violations->get(0)->getPropertyPath());
        }
    }

    public function testValidatesNestedRevisions(): void
    {
        $request = new ContentInlineMappingParseBatchRequest([
            new ContentInlineMappingParseRequest('Valid', 0),
            new ContentInlineMappingParseRequest('Invalid', -1),
        ]);
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        $violations = $validator->validate($request);

        static::assertCount(1, $violations);
        static::assertSame('requests[1].revision', $violations->get(0)->getPropertyPath());
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function batchSizeProvider(): iterable
    {
        yield 'empty batches are rejected' => [0, 1];
        yield 'one field is accepted' => [1, 0];
        yield 'maximum batch size is accepted' => [50, 0];
        yield 'more than fifty fields are rejected' => [51, 1];
    }
}
