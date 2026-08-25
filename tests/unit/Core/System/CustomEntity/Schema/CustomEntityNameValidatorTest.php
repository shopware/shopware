<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomEntity\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\CustomEntity\CustomEntityException;
use Shopware\Core\System\CustomEntity\Schema\CustomEntityNameValidator;

/**
 * @internal
 */
#[CoversClass(CustomEntityNameValidator::class)]
class CustomEntityNameValidatorTest extends TestCase
{
    /**
     * @param list<string> $fieldNames
     */
    #[DataProvider('forbiddenCharacterProvider')]
    public function testNamesWithForbiddenCharactersAreRejected(string $entityName, array $fieldNames, \Exception $expectedException): void
    {
        $this->expectExceptionObject($expectedException);

        (new CustomEntityNameValidator())->validate($entityName, $fieldNames);
    }

    /**
     * A name may only contain letters, digits and underscores. Any other character (whitespace or
     * punctuation) is what would let a name break out of its identifier position, so those are the
     * relevant cases to reject - no full statements needed to prove it.
     *
     * @return \Generator<string, array{0: string, 1: list<string>, 2: \Exception}>
     */
    public static function forbiddenCharacterProvider(): \Generator
    {
        yield 'whitespace in field name' => [
            'ce_blog',
            ['foo bar'],
            CustomEntityException::invalidFieldName('ce_blog', 'foo bar'),
        ];

        yield 'semicolon in field name' => [
            'ce_blog',
            ['foo;bar'],
            CustomEntityException::invalidFieldName('ce_blog', 'foo;bar'),
        ];

        yield 'parenthesis in field name' => [
            'ce_blog',
            ['foo(bar)'],
            CustomEntityException::invalidFieldName('ce_blog', 'foo(bar)'),
        ];

        yield 'backtick in entity name' => [
            'ce_blog`x',
            [],
            CustomEntityException::invalidEntityName('ce_blog`x'),
        ];

        yield 'quote in entity name' => [
            'ce_blog\'x',
            [],
            CustomEntityException::invalidEntityName('ce_blog\'x'),
        ];

        yield 'dash in field name' => [
            'ce_blog',
            ['foo-bar'],
            CustomEntityException::invalidFieldName('ce_blog', 'foo-bar'),
        ];
    }

    public function testValidNamesAreAccepted(): void
    {
        $this->expectNotToPerformAssertions();

        (new CustomEntityNameValidator())->validate(
            'ce_blog',
            ['top_seller_restrict', 'topSeller', '_internal', 'field2', '2fa_counter', 'price$usd']
        );
    }
}
