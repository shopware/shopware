<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Validation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Validation\HappyPathValidator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
class HappyPathValidatorTest extends TestCase
{
    /**
     * @dataProvider constraintDataProvider
     */
    public function testValidator(Constraint $constraint, $value, bool $isValid): void
    {
        $inner = $this->createMock(ValidatorInterface::class);

        if ($isValid) {
            $inner->expects(static::never())->method('validate');
        } else {
            $inner->expects(static::atLeastOnce())->method('validate')->willReturn(new ConstraintViolationList([
                $this->createMock(ConstraintViolationInterface::class),
            ]));
        }

        $validator = new HappyPathValidator($inner);
        $list = $validator->validate($value, $constraint);

        $isEmpty = $list->count() === 0;
        static::assertSame($isValid, $isEmpty);
    }

    public static function constraintDataProvider(): \Generator
    {
        yield 'min range valid' => [
            new Range(['min' => 11]),
            11,
            true,
        ];

        yield 'min range invalid' => [
            new Range(['min' => 11]),
            10,
            false,
        ];

        yield 'max range valid' => [
            new Range(['max' => 11]),
            11,
            true,
        ];

        yield 'max range invalid' => [
            new Range(['max' => 11]),
            12,
            false,
        ];

        yield 'min max range valid' => [
            new Range(['min' => 11, 'max' => 20]),
            20,
            true,
        ];

        yield 'min max range too low' => [
            new Range(['min' => 11, 'max' => 20]),
            10,
            false,
        ];

        yield 'min max range too high' => [
            new Range(['min' => 11, 'max' => 20]),
            21,
            false,
        ];

        yield 'check not-blank against whitespace value without normalizer' => [
            new NotBlank(),
            ' ',
            true,
        ];

        yield 'check not-blank against whitespace value with trim-normalizer' => [
            new NotBlank(['normalizer' => 'trim']),
            ' ',
            false,
        ];
    }
}
