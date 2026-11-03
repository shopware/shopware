<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\ContentInlineMappingParseRequest;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentInlineMappingParseRequest::class)]
class ContentInlineMappingParseRequestTest extends TestCase
{
    #[DataProvider('revisionProvider')]
    public function testRevisionMustBeNonNegative(int $revision, int $violationCount): void
    {
        $request = new ContentInlineMappingParseRequest('{{map:product.name}}', $revision);
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        $violations = $validator->validate($request);

        static::assertCount($violationCount, $violations);
        if ($violationCount > 0) {
            static::assertSame('revision', $violations->get(0)->getPropertyPath());
        }
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function revisionProvider(): iterable
    {
        yield 'negative revision is invalid' => [-1, 1];
        yield 'initial revision is valid' => [0, 0];
        yield 'later revision is valid' => [12, 0];
    }
}
