<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Meta\Metadata;
use Shopware\Core\Framework\App\Validation\AppRequirementsValidator;
use Shopware\Core\Framework\App\Validation\Error\UnmetRequirementError;
use Shopware\Core\Framework\App\Validation\Requirements\Requirement;
use Shopware\Core\Framework\App\Validation\Requirements\UnmetRequirement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppRequirementsValidator::class)]
class AppRequirementsValidatorTest extends TestCase
{
    public function testValidateWithSatisfiedRequirement(): void
    {
        $requirement = new class implements Requirement {
            public function validate(Manifest $manifest): ?UnmetRequirement
            {
                return null;
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

        $validator = new AppRequirementsValidator([$requirement], static::createStub(LoggerInterface::class), 'prod');
        $manifest = $this->createMock(Manifest::class);
        $manifest->expects($this->once())->method('getRequirements')->willReturn(['test-requirement']);

        static::assertCount(0, $validator->validate($manifest, null));
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
        $manifest->expects($this->once())->method('getRequirements')->willReturn(['test-requirement']);
        $validator = new AppRequirementsValidator([$requirement], static::createStub(LoggerInterface::class), 'prod');

        $errors = $validator->validate($manifest, null);

        static::assertCount(1, $errors);
        $error = $errors[0];
        static::assertInstanceOf(UnmetRequirementError::class, $error);
        static::assertSame(
            'The app requirements are not met: App "test-app" - Requirement "test-requirement": Fix the test requirement',
            $error->getMessage()
        );
    }

    public function testValidateWithNotRequiredValidator(): void
    {
        $requirement = new class implements Requirement {
            public int $validateCalls = 0;

            public function validate(Manifest $manifest): ?UnmetRequirement
            {
                ++$this->validateCalls;

                return null;
            }

            public function required(Manifest $manifest): bool
            {
                return false;
            }

            public static function name(): string
            {
                return 'test-requirement';
            }
        };

        $validator = new AppRequirementsValidator([$requirement], static::createStub(LoggerInterface::class), 'prod');
        $manifest = $this->createMock(Manifest::class);
        $manifest->expects($this->once())->method('getRequirements')->willReturn(['test-requirement']);

        static::assertCount(0, $validator->validate($manifest, null));
        static::assertSame(0, $requirement->validateCalls);
    }

    public function testValidateWithMultipleValidators(): void
    {
        $requirement1 = new class implements Requirement {
            public function validate(Manifest $manifest): ?UnmetRequirement
            {
                return null;
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

        $requirement3 = new class implements Requirement {
            public int $validateCalls = 0;

            public function validate(Manifest $manifest): ?UnmetRequirement
            {
                ++$this->validateCalls;

                return null;
            }

            public function required(Manifest $manifest): bool
            {
                return false;
            }

            public static function name(): string
            {
                return 'requirement-3';
            }
        };

        $manifest = $this->createMock(Manifest::class);
        $manifest->expects($this->once())->method('getRequirements')->willReturn(['requirement-1', 'requirement-2']);

        $validator = new AppRequirementsValidator([$requirement1, $requirement2, $requirement3], static::createStub(LoggerInterface::class), 'prod');

        $errors = $validator->validate($manifest, null);

        static::assertCount(1, $errors);
        $error = $errors[0];
        static::assertInstanceOf(UnmetRequirementError::class, $error);
        static::assertSame(
            'The app requirements are not met: App "multi-app" - Requirement "requirement-2": Fix requirement 2',
            $error->getMessage()
        );
        static::assertSame(0, $requirement3->validateCalls);
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
        $manifest->expects($this->once())->method('getRequirements')->willReturn(['requirement-1', 'requirement-2']);

        $validator = new AppRequirementsValidator([$requirement1, $requirement2], static::createStub(LoggerInterface::class), 'prod');

        $errors = $validator->validate($manifest, null);

        // every violation is reported through a single error
        static::assertCount(1, $errors);
        $error = $errors[0];
        static::assertInstanceOf(UnmetRequirementError::class, $error);
        static::assertSame(
            'The app requirements are not met: App "violation-app" - Requirement "requirement-1": Fix requirement 1; App "violation-app" - Requirement "requirement-2": Fix requirement 2',
            $error->getMessage()
        );
    }

    public function testValidateSkipsInNonProdEnvironment(): void
    {
        $requirement = new class implements Requirement {
            public int $validateCalls = 0;

            public int $requiredCalls = 0;

            public function validate(Manifest $manifest): ?UnmetRequirement
            {
                ++$this->validateCalls;

                return null;
            }

            public function required(Manifest $manifest): bool
            {
                ++$this->requiredCalls;

                return true;
            }

            public static function name(): string
            {
                return 'test-requirement';
            }
        };

        $validator = new AppRequirementsValidator([$requirement], static::createStub(LoggerInterface::class), 'dev');
        $manifest = $this->createMock(Manifest::class);
        $manifest->expects($this->once())->method('getRequirements')->willReturn(['test-requirement']);

        static::assertCount(0, $validator->validate($manifest, null));
        static::assertSame(0, $requirement->requiredCalls);
        static::assertSame(0, $requirement->validateCalls);
    }

    public function testValidateLogsUnknownRequirements(): void
    {
        $manifest = $this->createMock(Manifest::class);
        $manifest->expects($this->once())->method('getRequirements')->willReturn(['custom-private-requirement', 'test-requirement']);
        $manifest->expects($this->once())->method('getMetadata')->willReturn(Metadata::fromArray([
            'name' => 'test-app',
            'label' => [],
            'author' => 'shopware',
            'copyright' => 'shopware',
            'license' => 'MIT',
            'version' => '1.0.0',
        ]));

        $requirement = new class implements Requirement {
            public function validate(Manifest $manifest): ?UnmetRequirement
            {
                return null;
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

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                'App manifest declares unsupported requirement "{requirementName}" for app "{appName}". The requirement will be ignored until a matching validator tagged with "app.requirements_validator" is registered.',
                [
                    'requirementName' => 'custom-private-requirement',
                    'appName' => 'test-app',
                ]
            );

        $validator = new AppRequirementsValidator([$requirement], $logger, 'prod');

        static::assertCount(0, $validator->validate($manifest, null));
    }
}
