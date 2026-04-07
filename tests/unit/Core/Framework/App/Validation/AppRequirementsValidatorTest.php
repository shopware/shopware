<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Meta\Metadata;
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

        $validator = new AppRequirementsValidator([$requirement], $this->createMock(LoggerInterface::class), 'prod');
        $manifest = $this->createMock(Manifest::class);
        $manifest->expects($this->once())->method('getRequirements')->willReturn(['test-requirement']);

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
        $manifest->expects($this->once())->method('getRequirements')->willReturn(['test-requirement']);
        $validator = new AppRequirementsValidator([$requirement], $this->createMock(LoggerInterface::class), 'prod');

        $violations = $validator->validate($manifest);

        static::assertCount(1, $violations);
        static::assertInstanceOf(UnmetRequirement::class, $violations[0]);
        static::assertSame('test-app', $violations[0]->appName);
        static::assertSame('test-requirement', $violations[0]->requirementName);
        static::assertSame('Fix the test requirement', $violations[0]->actionableResolution);
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

        $validator = new AppRequirementsValidator([$requirement], $this->createMock(LoggerInterface::class), 'prod');
        $manifest = $this->createMock(Manifest::class);
        $manifest->expects($this->once())->method('getRequirements')->willReturn(['test-requirement']);

        $violations = $validator->validate($manifest);

        static::assertSame([], $violations);
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

        $validator = new AppRequirementsValidator([$requirement1, $requirement2, $requirement3], $this->createMock(LoggerInterface::class), 'prod');

        $violations = $validator->validate($manifest);

        static::assertCount(1, $violations);
        static::assertSame('multi-app', $violations[0]->appName);
        static::assertSame('requirement-2', $violations[0]->requirementName);
        static::assertSame('Fix requirement 2', $violations[0]->actionableResolution);
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

        $validator = new AppRequirementsValidator([$requirement1, $requirement2], $this->createMock(LoggerInterface::class), 'prod');

        $violations = $validator->validate($manifest);

        static::assertCount(2, $violations);

        static::assertSame('violation-app', $violations[0]->appName);
        static::assertSame('requirement-1', $violations[0]->requirementName);
        static::assertSame('Fix requirement 1', $violations[0]->actionableResolution);

        static::assertSame('violation-app', $violations[1]->appName);
        static::assertSame('requirement-2', $violations[1]->requirementName);
        static::assertSame('Fix requirement 2', $violations[1]->actionableResolution);
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

        $validator = new AppRequirementsValidator([$requirement], $this->createMock(LoggerInterface::class), 'dev');
        $manifest = $this->createMock(Manifest::class);
        $manifest->expects($this->once())->method('getRequirements')->willReturn(['test-requirement']);

        static::assertSame([], $validator->validate($manifest));
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

        static::assertSame([], $validator->validate($manifest));
    }
}
