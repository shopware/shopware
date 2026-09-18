<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Validation\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Framework\Validation\Constraint\NoHtml;
use Shopware\Core\Framework\Validation\Constraint\NoHtmlValidator;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @internal
 *
 * @extends ConstraintValidatorTestCase<NoHtmlValidator>
 */
#[Package('framework')]
#[CoversClass(NoHtmlValidator::class)]
class NoHtmlValidatorTest extends ConstraintValidatorTestCase
{
    /**
     * @return \Generator<string, array{0: string|null}>
     */
    public static function valuesWithoutHtmlProvider(): \Generator
    {
        yield 'a plain name is untouched' => ['John'];
        yield 'a less-than sign followed by a digit does not open a tag' => ['I <3 you'];
        yield 'a stand-alone greater-than sign is not markup' => ['5 > 3'];
        yield 'null is left to the constraints that check presence' => [null];
        yield 'an empty string is left to the constraints that check presence' => [''];
    }

    #[DataProvider('valuesWithoutHtmlProvider')]
    public function testItAcceptsValuesWithoutHtml(?string $value): void
    {
        $this->validator->validate($value, new NoHtml());

        $this->assertNoViolation();
    }

    /**
     * @return \Generator<string, array{0: string}>
     */
    public static function valuesWithHtmlProvider(): \Generator
    {
        yield 'a less-than sign followed by a letter opens a tag and is dropped' => ['<John'];
        yield 'inline markup around a name is dropped' => ['John <b>Doe</b>'];
        yield 'a script element loses its content as well' => ['<script>x</script>'];
    }

    #[DataProvider('valuesWithHtmlProvider')]
    public function testItRejectsValuesContainingHtml(string $value): void
    {
        $constraint = new NoHtml();

        $this->validator->validate($value, $constraint);

        $this->buildViolation($constraint->getMessage())
            ->setCode(NoHtml::CONTAINS_HTML_ERROR)
            ->assertRaised();
    }

    public function testItUsesTheConfiguredMessageForTheViolation(): void
    {
        $constraint = new NoHtml(message: 'VIOLATION::CONTAINS_HTML_ERROR');

        $this->validator->validate('<John', $constraint);

        $this->buildViolation('VIOLATION::CONTAINS_HTML_ERROR')
            ->setCode(NoHtml::CONTAINS_HTML_ERROR)
            ->assertRaised();
    }

    public function testItRejectsAnUnexpectedConstraintType(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate('John', new NotBlank());
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        return new NoHtmlValidator(new HtmlSanitizer(cacheEnabled: false));
    }
}
