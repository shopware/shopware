<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\ContentLayoutUpdateElementPropertiesRequest;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The DTO's own constraint attributes, evaluated the way `#[MapRequestPayload]` evaluates them at the HTTP
 * boundary: against the class metadata, which is what an attribute-mapped validator builds here. The key and
 * value rules the operation owns need the type registry and are not reachable from this surface.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentLayoutUpdateElementPropertiesRequest::class)]
class ContentLayoutUpdateElementPropertiesRequestTest extends TestCase
{
    #[TestDox('rejects a request that writes nothing and removes nothing, on the values property')]
    public function testRejectsARequestWithBothListsEmpty(): void
    {
        $request = new ContentLayoutUpdateElementPropertiesRequest(elementId: 'block-a', expectedVersion: null);

        $violations = $this->validator()->validate($request);

        static::assertCount(1, $violations);
        static::assertSame('values', $violations->get(0)->getPropertyPath());
        static::assertSame(
            'An update-element-properties request must carry at least one entry in "values" or "removeKeys" (updateElementPropertiesEmpty).',
            (string) $violations->get(0)->getMessage()
        );
    }

    /**
     * @param list<mixed> $removeKeys
     */
    #[DataProvider('rejectedRemoveKeysProvider')]
    #[TestDox('rejects $_dataName in removeKeys')]
    public function testRejectsAMalformedRemovalList(array $removeKeys, string $expectedPropertyPath, string $expectedMessage): void
    {
        $request = new ContentLayoutUpdateElementPropertiesRequest(elementId: 'block-a', expectedVersion: null, removeKeys: $removeKeys);

        $violations = $this->validator()->validate($request);

        static::assertCount(1, $violations);
        static::assertSame($expectedPropertyPath, $violations->get(0)->getPropertyPath());
        static::assertSame($expectedMessage, (string) $violations->get(0)->getMessage());
    }

    /**
     * @return iterable<string, array{list<mixed>, string, string}>
     */
    public static function rejectedRemoveKeysProvider(): iterable
    {
        yield 'a non-string entry' => [[1], 'removeKeys[0]', 'This value should be of type string.'];
        yield 'a blank entry' => [[''], 'removeKeys[0]', 'This value should not be blank.'];
        yield 'a duplicate entry' => [['tag', 'tag'], 'removeKeys', 'This collection should contain only unique elements.'];
    }

    private function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }
}
