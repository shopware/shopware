<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\ContentLayoutCreateDraftRequest;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentLayoutCreateDraftRequest::class)]
class ContentLayoutCreateDraftRequestTest extends TestCase
{
    #[TestDox('both fields are optional, so an empty body opens an unnamed draft of the live revision')]
    public function testBothFieldsAreOptional(): void
    {
        $request = new ContentLayoutCreateDraftRequest();

        static::assertNull($request->name);
        static::assertNull($request->fromRevisionId);
        static::assertCount(0, Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($request));
    }

    #[TestDox('carries the draft name and the revision to open the draft from')]
    public function testCarriesTheNameAndTheRevision(): void
    {
        $request = new ContentLayoutCreateDraftRequest('Summer campaign', 'revision-1');

        static::assertSame('Summer campaign', $request->name);
        static::assertSame('revision-1', $request->fromRevisionId);
    }

    #[DataProvider('invalidNames')]
    #[TestDox('rejects $_dataName')]
    public function testRejectsAnInvalidName(string $name): void
    {
        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()
            ->validate(new ContentLayoutCreateDraftRequest($name));

        static::assertCount(1, $violations);
        static::assertSame('name', $violations->get(0)->getPropertyPath());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNames(): iterable
    {
        yield 'an empty name' => [''];
        yield 'a whitespace-only name' => ['   '];
        yield 'a name longer than the 255-character column' => [str_repeat('a', 256)];
    }
}
