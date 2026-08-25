<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\ContentPreviewRequest;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The DTO's own constraint attributes, evaluated the way both sides evaluate them: `#[MapRequestPayload]` at
 * the HTTP boundary and `ContentPreviewPayloadStore::assertDeclaredConstraints()` on redemption both validate
 * the object against its class metadata, which is what an attribute-mapped validator builds here.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentPreviewRequest::class)]
class ContentPreviewRequestTest extends TestCase
{
    /**
     * @param array<array-key, mixed> $queryParameters
     */
    #[DataProvider('rejectedQueryParameterKeyProvider')]
    #[TestDox('rejects $_dataName as a query parameter name')]
    public function testRejectsAQueryParameterNameThatIsNotAString(array $queryParameters, string $expectedMessage): void
    {
        $violations = $this->validator()->validate($this->request($queryParameters));

        static::assertCount(1, $violations);
        static::assertSame('queryParameters', $violations->get(0)->getPropertyPath());
        static::assertSame($expectedMessage, (string) $violations->get(0)->getMessage());
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function rejectedQueryParameterKeyProvider(): iterable
    {
        yield 'the JSON member name "0"' => [
            json_decode('{"0":"x"}', true, 512, \JSON_THROW_ON_ERROR),
            'Query parameter name "0" must be a string.',
        ];

        yield 'the JSON member name "12"' => [
            json_decode('{"12":"x"}', true, 512, \JSON_THROW_ON_ERROR),
            'Query parameter name "12" must be a string.',
        ];

        yield 'the JSON member name "-3"' => [
            json_decode('{"-3":"x"}', true, 512, \JSON_THROW_ON_ERROR),
            'Query parameter name "-3" must be a string.',
        ];
    }

    /**
     * @param array<array-key, mixed> $queryParameters
     */
    #[DataProvider('acceptedQueryParameterKeyProvider')]
    #[TestDox('accepts $_dataName as a query parameter name')]
    public function testAcceptsAQueryParameterNameThatStaysAString(array $queryParameters): void
    {
        static::assertCount(0, $this->validator()->validate($this->request($queryParameters)));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function acceptedQueryParameterKeyProvider(): iterable
    {
        yield 'an ordinary name' => [['elementId' => 'el-1']];

        // PHP casts a key to an integer only in canonical decimal form, so each of these stays a string key.
        yield 'the leading-zero name "012"' => [json_decode('{"012":"x"}', true, 512, \JSON_THROW_ON_ERROR)];

        yield 'the decimal name "1.0"' => [json_decode('{"1.0":"x"}', true, 512, \JSON_THROW_ON_ERROR)];

        yield 'an empty name' => [json_decode('{"":"x"}', true, 512, \JSON_THROW_ON_ERROR)];
    }

    /**
     * @param array<array-key, mixed> $queryParameters
     */
    private function request(array $queryParameters): ContentPreviewRequest
    {
        return new ContentPreviewRequest(
            layout: [['id' => 'el-1', 'component' => 'Sw:Block']],
            entityType: 'product',
            entityId: 'prod-1',
            salesChannelId: 'sales-channel-1',
            queryParameters: $queryParameters,
        );
    }

    private function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }
}
