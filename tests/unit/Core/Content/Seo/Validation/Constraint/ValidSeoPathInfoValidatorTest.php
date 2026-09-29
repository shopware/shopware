<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\Validation\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoException;
use Shopware\Core\Content\Seo\Validation\Constraint\ValidSeoPathInfo;
use Shopware\Core\Content\Seo\Validation\Constraint\ValidSeoPathInfoValidator;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ValidSeoPathInfoValidator::class)]
class ValidSeoPathInfoValidatorTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string}>
     */
    public static function validValues(): iterable
    {
        yield 'simple path' => ['Computers/Laptops'];
        yield 'with hyphen and digits' => ['Pepper-white-ground-pearl/SW10098'];
        yield 'with unicode letters' => ['café/über'];
        yield 'with dot and tilde' => ['a.b~c'];
        yield 'with query string' => ['seo/url?foo=bar'];
        yield 'with valid percent-escape' => ['seo/%20/foo'];
        yield 'with encoded unicode' => ['caf%C3%A9/SW10098'];
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidValues(): iterable
    {
        yield 'stray percent' => ['seo/url%/1'];
        yield 'incomplete percent-escape' => ['seo/url%4/1'];
        yield 'fragment' => ['seo/url#anchor'];
        yield 'backslash' => ['seo\\url'];
        yield 'control character NUL' => ["seo\0url"];
        yield 'control character newline' => ["seo\nurl"];
    }

    #[DataProvider('validValues')]
    public function testValidValuesPass(string $value): void
    {
        $violations = $this->buildValidator()
            ->validate($value, new ValidSeoPathInfo());

        static::assertCount(0, $violations);
    }

    #[DataProvider('invalidValues')]
    public function testInvalidValuesAreRejected(string $value): void
    {
        $violations = $this->buildValidator()
            ->validate($value, new ValidSeoPathInfo());

        static::assertCount(1, $violations);

        $violation = $violations->get(0);
        static::assertInstanceOf(ConstraintViolation::class, $violation);
        static::assertSame(ValidSeoPathInfo::INVALID_CHARACTERS, $violation->getCode());
    }

    public function testEmptyStringIsIgnored(): void
    {
        $violations = $this->buildValidator()
            ->validate('', new ValidSeoPathInfo());

        static::assertCount(0, $violations);
    }

    public function testNullIsIgnored(): void
    {
        $violations = $this->buildValidator()
            ->validate(null, new ValidSeoPathInfo());

        static::assertCount(0, $violations);
    }

    public function testNonStringValueReportsTypeViolation(): void
    {
        $violations = $this->buildValidator()
            ->validate(123, new ValidSeoPathInfo());

        static::assertCount(1, $violations);
        static::assertSame(ValidSeoPathInfo::INVALID_TYPE_MESSAGE, $violations->get(0)->getMessageTemplate());
    }

    private function buildValidator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()
            ->setConstraintValidatorFactory(new class implements ConstraintValidatorFactoryInterface {
                public function getInstance(Constraint $constraint): ConstraintValidatorInterface
                {
                    if ($constraint instanceof ValidSeoPathInfo) {
                        return new ValidSeoPathInfoValidator();
                    }

                    throw SeoException::unexpectedType($constraint, ValidSeoPathInfo::class);
                }
            })
            ->getValidator();
    }
}
