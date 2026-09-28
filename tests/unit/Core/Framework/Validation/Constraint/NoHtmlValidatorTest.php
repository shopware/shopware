<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Validation\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Framework\Validation\Constraint\NoHtml;
use Shopware\Core\Framework\Validation\Constraint\NoHtmlValidator;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(NoHtmlValidator::class)]
class NoHtmlValidatorTest extends TestCase
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
        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->never())->method('buildViolation');

        $this->createValidator($context)->validate($value, new NoHtml());
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

        $this->createValidator($this->expectViolation($constraint->getMessage()))->validate($value, $constraint);
    }

    public function testItUsesTheConfiguredMessageForTheViolation(): void
    {
        $constraint = new NoHtml(message: 'VIOLATION::CONTAINS_HTML_ERROR');

        $this->createValidator($this->expectViolation('VIOLATION::CONTAINS_HTML_ERROR'))->validate('<John', $constraint);
    }

    public function testItRejectsAnUnexpectedConstraintType(): void
    {
        $validator = $this->createValidator($this->createMock(ExecutionContextInterface::class));

        $this->expectException(UnexpectedTypeException::class);

        $validator->validate('John', new NotBlank());
    }

    private function expectViolation(string $message): ExecutionContextInterface&MockObject
    {
        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->expects($this->once())->method('setCode')->with(NoHtml::CONTAINS_HTML_ERROR)->willReturnSelf();
        $builder->expects($this->once())->method('addViolation');

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->expects($this->once())->method('buildViolation')->with($message)->willReturn($builder);

        return $context;
    }

    private function createValidator(ExecutionContextInterface $context): NoHtmlValidator
    {
        $validator = new NoHtmlValidator(new HtmlSanitizer(cacheEnabled: false));
        $validator->initialize($context);

        return $validator;
    }
}
