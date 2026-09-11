<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\UpdateElementPropertiesRequest;
use Shopware\Core\Framework\ContentSystem\Api\Validation\UpdateElementPropertiesNotEmpty;
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
#[CoversClass(UpdateElementPropertiesRequest::class)]
class UpdateElementPropertiesRequestTest extends TestCase
{
    #[TestDox('rejects a request that writes nothing and removes nothing, on the values property')]
    public function testRejectsARequestWithBothListsEmpty(): void
    {
        $violations = $this->validator()->validate(new UpdateElementPropertiesRequest(elementId: 'block-a'));

        static::assertCount(1, $violations);
        static::assertSame('values', $violations->get(0)->getPropertyPath());
        // The wording itself is pinned by the validator test; this pins that THIS route's DTO carries the rule.
        static::assertSame((new UpdateElementPropertiesNotEmpty())->message, (string) $violations->get(0)->getMessage());
    }

    /**
     * @param list<mixed> $removeKeys
     */
    #[DataProvider('rejectedRemoveKeysProvider')]
    #[TestDox('rejects $_dataName in removeKeys')]
    public function testRejectsAMalformedRemovalList(array $removeKeys, string $expectedPropertyPath, string $expectedMessage): void
    {
        $request = new UpdateElementPropertiesRequest(elementId: 'block-a', removeKeys: $removeKeys);

        $violations = $this->validator()->validate($request);

        static::assertCount(1, $violations);
        static::assertSame($expectedPropertyPath, $violations->get(0)->getPropertyPath());
        static::assertSame($expectedMessage, (string) $violations->get(0)->getMessage());
    }

    #[TestDox('accepts a request that writes a value and removes nothing')]
    public function testAcceptsARequestCarryingOnlyValues(): void
    {
        $request = new UpdateElementPropertiesRequest(elementId: 'block-a', values: ['headline' => 'Hi']);

        $violations = $this->validator()->validate($request);

        static::assertCount(0, $violations);
    }

    #[TestDox('accepts a request that writes nothing and removes a key')]
    public function testAcceptsARequestCarryingOnlyRemoveKeys(): void
    {
        $request = new UpdateElementPropertiesRequest(elementId: 'block-a', removeKeys: ['tag']);

        $violations = $this->validator()->validate($request);

        static::assertCount(0, $violations);
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
