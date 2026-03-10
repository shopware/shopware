<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\AppRequirementsValidator;
use Shopware\Core\Framework\App\Validation\Requirements\Requirement;
use Shopware\Core\Framework\App\Validation\Requirements\UnmetRequirement;

/**
 * @internal
 */
#[CoversClass(AppRequirementsValidator::class)]
class AppRequirementsValidatorTest extends TestCase
{
    public function testValidateWithSatisfiedRequirement(): void
    {
        $requirement = $this->createMock(Requirement::class);
        $requirement->expects($this->once())
            ->method('required')
            ->willReturn(true);
        $requirement->expects($this->once())
            ->method('validate')
            ->willReturn(null);

        $validator = new AppRequirementsValidator([$requirement], 'prod');
        $manifest = $this->createMock(Manifest::class);

        $violations = $validator->validate($manifest);

        static::assertSame([], $violations);
    }

    public function testValidateWithUnsatisfiedRequirement(): void
    {
        $requirement = new class implements Requirement {
            public function validate(Manifest $manifest): UnmetRequirement
            {
                return new UnmetRequirement('test-app', self::name(), 'Fix the test requirement');
            }

            public function required(Manifest $manifest): bool
            {
                return true;
            }

            public static function name(): string
            {
                return 'test-requirement';
            }
        };

        $manifest = $this->createMock(Manifest::class);
        $validator = new AppRequirementsValidator([$requirement], 'prod');

        $violations = $validator->validate($manifest);

        static::assertCount(1, $violations);
        static::assertInstanceOf(UnmetRequirement::class, $violations[0]);
        static::assertSame('test-app', $violations[0]->appName);
        static::assertSame('test-requirement', $violations[0]->requirementName);
        static::assertSame('Fix the test requirement', $violations[0]->actionableResolution);
    }

    public function testValidateWithNotRequiredValidator(): void
    {
        $requirement = $this->createMock(Requirement::class);
        $requirement->expects($this->once())
            ->method('required')
            ->willReturn(false);
        $requirement->expects($this->never())
            ->method('validate');

        $validator = new AppRequirementsValidator([$requirement], 'prod');
        $manifest = $this->createMock(Manifest::class);

        $violations = $validator->validate($manifest);

        static::assertSame([], $violations);
    }

    public function testValidateWithMultipleValidators(): void
    {
        $requirement1 = $this->createMock(Requirement::class);
        $requirement1->expects($this->once())
            ->method('required')
            ->willReturn(true);
        $requirement1->expects($this->once())
            ->method('validate')
            ->willReturn(null);

        $requirement2 = new class implements Requirement {
            public function validate(Manifest $manifest): UnmetRequirement
            {
                return new UnmetRequirement('multi-app', self::name(), 'Fix requirement 2');
            }

            public function required(Manifest $manifest): bool
            {
                return true;
            }

            public static function name(): string
            {
                return 'requirement-2';
            }
        };

        $requirement3 = $this->createMock(Requirement::class);
        $requirement3->expects($this->once())
            ->method('required')
            ->willReturn(false);
        $requirement3->expects($this->never())
            ->method('validate');

        $manifest = $this->createMock(Manifest::class);

        $validator = new AppRequirementsValidator([$requirement1, $requirement2, $requirement3], 'prod');

        $violations = $validator->validate($manifest);

        static::assertCount(1, $violations);
        static::assertSame('multi-app', $violations[0]->appName);
        static::assertSame('requirement-2', $violations[0]->requirementName);
        static::assertSame('Fix requirement 2', $violations[0]->actionableResolution);
    }

    public function testValidateWithMultipleViolations(): void
    {
        $requirement1 = new class implements Requirement {
            public function validate(Manifest $manifest): UnmetRequirement
            {
                return new UnmetRequirement('violation-app', self::name(), 'Fix requirement 1');
            }

            public function required(Manifest $manifest): bool
            {
                return true;
            }

            public static function name(): string
            {
                return 'requirement-1';
            }
        };

        $requirement2 = new class implements Requirement {
            public function validate(Manifest $manifest): UnmetRequirement
            {
                return new UnmetRequirement('violation-app', self::name(), 'Fix requirement 2');
            }

            public function required(Manifest $manifest): bool
            {
                return true;
            }

            public static function name(): string
            {
                return 'requirement-2';
            }
        };

        $manifest = $this->createMock(Manifest::class);

        $validator = new AppRequirementsValidator([$requirement1, $requirement2], 'prod');

        $violations = $validator->validate($manifest);

        static::assertCount(2, $violations);

        static::assertSame('violation-app', $violations[0]->appName);
        static::assertSame('requirement-1', $violations[0]->requirementName);
        static::assertSame('Fix requirement 1', $violations[0]->actionableResolution);

        static::assertSame('violation-app', $violations[1]->appName);
        static::assertSame('requirement-2', $violations[1]->requirementName);
        static::assertSame('Fix requirement 2', $violations[1]->actionableResolution);
    }

    public function testValidateSkipsInDevEnvironment(): void
    {
        $requirement = $this->createMock(Requirement::class);
        $requirement->expects($this->never())->method('required');
        $requirement->expects($this->never())->method('validate');

        $validator = new AppRequirementsValidator([$requirement], 'dev');
        $manifest = $this->createMock(Manifest::class);

        static::assertSame([], $validator->validate($manifest));
    }

    public function testValidateSkipsInTestEnvironment(): void
    {
        $requirement = $this->createMock(Requirement::class);
        $requirement->expects($this->never())->method('required');
        $requirement->expects($this->never())->method('validate');

        $validator = new AppRequirementsValidator([$requirement], 'test');
        $manifest = $this->createMock(Manifest::class);

        static::assertSame([], $validator->validate($manifest));
    }
}
