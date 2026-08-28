<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Requirement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\Requirement\Gate;
use Shopware\Core\Service\Requirement\RequirementsValidator;
use Shopware\Core\Service\Requirement\ServiceRequirement;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RequirementsValidator::class)]
class RequirementsValidatorTest extends TestCase
{
    public function testSatisfiedWhenAllRequirementsOfTheGateAreMet(): void
    {
        $requirements = new RequirementsValidator(new \ArrayIterator([
            'services_enabled' => $this->requirement(Gate::INSTALLATION, true),
            'service_consent' => $this->requirement(Gate::PRIVILEGES, true),
        ]));

        static::assertTrue($requirements->isSatisfied(['services_enabled', 'service_consent'], Gate::INSTALLATION));
    }

    public function testUnsatisfiedWhenARequirementOfTheGateIsNotMet(): void
    {
        $requirements = new RequirementsValidator(new \ArrayIterator([
            'services_enabled' => $this->requirement(Gate::INSTALLATION, false),
            'service_consent' => $this->requirement(Gate::PRIVILEGES, true),
        ]));

        static::assertFalse($requirements->isSatisfied(['services_enabled', 'service_consent'], Gate::INSTALLATION));
    }

    public function testRequirementsOfAnotherGateAreIgnored(): void
    {
        $requirements = new RequirementsValidator(new \ArrayIterator([
            'services_enabled' => $this->requirement(Gate::INSTALLATION, true),
            'service_consent' => $this->requirement(Gate::PRIVILEGES, false),
        ]));

        // service_consent is a Privileges requirement and not satisfied, but for the Installation gate it doesn't count
        static::assertTrue($requirements->isSatisfied(['services_enabled', 'service_consent'], Gate::INSTALLATION));
        // ...and for the Privileges gate it does
        static::assertFalse($requirements->isSatisfied(['services_enabled', 'service_consent'], Gate::PRIVILEGES));
    }

    public function testUnknownRequirementIsNeverSatisfied(): void
    {
        $requirements = new RequirementsValidator(new \ArrayIterator([
            'service_consent' => $this->requirement(Gate::PRIVILEGES, true),
        ]));

        static::assertFalse($requirements->isSatisfied(['service_consent', 'mystery'], Gate::INSTALLATION));
        static::assertFalse($requirements->isSatisfied(['service_consent', 'mystery'], Gate::PRIVILEGES));
    }

    public function testNoneGatedRequirementGatesNeitherEvenWhenUnsatisfied(): void
    {
        // a recognised marker requirement: known (so it doesn't count as unknown), but gates nothing
        $requirements = new RequirementsValidator(new \ArrayIterator([
            'a_marker' => $this->requirement(Gate::NONE, false),
        ]));

        static::assertTrue($requirements->isSatisfied(['a_marker'], Gate::INSTALLATION));
        static::assertTrue($requirements->isSatisfied(['a_marker'], Gate::PRIVILEGES));
    }

    public function testStateChangePermittedWhenAllRequirementsPermitIt(): void
    {
        $requirements = new RequirementsValidator(new \ArrayIterator([
            'services_enabled' => $this->requirement(Gate::INSTALLATION, true),
            'service_consent' => $this->requirement(Gate::PRIVILEGES, true),
        ]));

        static::assertTrue($requirements->permitsStateChange(['services_enabled', 'service_consent']));
        static::assertTrue($requirements->permitsStateChange([]));
    }

    public function testStateChangeNotPermittedWhenAnyRequirementForbidsIt(): void
    {
        $requirements = new RequirementsValidator(new \ArrayIterator([
            'service_consent' => $this->requirement(Gate::PRIVILEGES, true),
            'shopware_account' => $this->requirement(Gate::PRIVILEGES, true, permitsStateChange: false),
        ]));

        static::assertFalse($requirements->permitsStateChange(['service_consent', 'shopware_account']));
        static::assertTrue($requirements->permitsStateChange(['service_consent']));
    }

    public function testStateChangePermissionIsIndependentOfSatisfaction(): void
    {
        // the policy is about who controls the state, not about whether the requirement is currently met
        $requirements = new RequirementsValidator(new \ArrayIterator([
            'shopware_account' => $this->requirement(Gate::PRIVILEGES, false, permitsStateChange: false),
        ]));

        static::assertFalse($requirements->permitsStateChange(['shopware_account']));
    }

    public function testStateChangeNotPermittedForUnknownRequirement(): void
    {
        // fail closed: a service declaring a requirement we don't model is never manually togglable
        $requirements = new RequirementsValidator(new \ArrayIterator([
            'service_consent' => $this->requirement(Gate::PRIVILEGES, true),
        ]));

        static::assertFalse($requirements->permitsStateChange(['service_consent', 'mystery']));
    }

    private function requirement(Gate $gate, bool $satisfied, bool $permitsStateChange = true): ServiceRequirement
    {
        return new class($gate, $satisfied, $permitsStateChange) implements ServiceRequirement {
            public function __construct(
                private readonly Gate $gate,
                private readonly bool $satisfied,
                private readonly bool $permitsStateChange,
            ) {
            }

            public static function getName(): string
            {
                return 'test';
            }

            public function getGate(): Gate
            {
                return $this->gate;
            }

            public function isSatisfied(): bool
            {
                return $this->satisfied;
            }

            public function permitsStateChange(): bool
            {
                return $this->permitsStateChange;
            }
        };
    }
}
