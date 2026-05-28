<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Requirement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Service\Requirement\RequirementsValidator;
use Shopware\Core\Service\Requirement\ServiceRequirement;

/**
 * @internal
 */
#[CoversClass(RequirementsValidator::class)]
class RequirementsValidatorTest extends TestCase
{
    public function testIsValidSetReturnsTrueWhenAllRequirementsAreKnown(): void
    {
        $validator = new RequirementsValidator(new \ArrayIterator([
            'service_consent' => $this->createRequirement(true),
            'shopware_account' => $this->createRequirement(true),
        ]));

        static::assertTrue($validator->isValidSet(['service_consent', 'shopware_account']));
    }

    public function testIsValidSetReturnsFalseWhenRequirementIsUnknown(): void
    {
        $validator = new RequirementsValidator(new \ArrayIterator([
            'service_consent' => $this->createRequirement(true),
        ]));

        static::assertFalse($validator->isValidSet(['service_consent', 'unknown_requirement']));
    }

    public function testIsSatisfiedReturnsTrueWhenAllMet(): void
    {
        $validator = new RequirementsValidator(new \ArrayIterator([
            'service_consent' => $this->createRequirement(true),
            'shopware_account' => $this->createRequirement(true),
        ]));

        static::assertTrue($validator->isSatisfied(['service_consent', 'shopware_account']));
    }

    public function testIsSatisfiedReturnsFalseWhenAnyNotMet(): void
    {
        $validator = new RequirementsValidator(new \ArrayIterator([
            'service_consent' => $this->createRequirement(true),
            'shopware_account' => $this->createRequirement(false),
        ]));

        static::assertFalse($validator->isSatisfied(['service_consent', 'shopware_account']));
    }

    public function testIsSatisfiedReturnsFalseForUnknown(): void
    {
        $validator = new RequirementsValidator(new \ArrayIterator([
            'service_consent' => $this->createRequirement(true),
        ]));

        static::assertFalse($validator->isSatisfied(['service_consent', 'unknown_requirement']));
    }

    private function createRequirement(bool $satisfied): ServiceRequirement
    {
        return new class($satisfied) implements ServiceRequirement {
            public function __construct(
                private readonly bool $satisfied,
            ) {
            }

            public static function getName(): string
            {
                return 'test';
            }

            public function isSatisfied(): bool
            {
                return $this->satisfied;
            }
        };
    }
}
