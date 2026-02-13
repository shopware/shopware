<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Requirement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Service\Requirement\RequirementsValidator;
use Shopware\Core\Service\Requirement\ServiceRequirement;

/**
 * @internal
 */
#[CoversClass(RequirementsValidator::class)]
class RequirementsValidatorTest extends TestCase
{
    public function testIsSatisfiedReturnsTrueWhenAllMet(): void
    {
        $validator = new RequirementsValidator(new \ArrayIterator([
            'service_consent' => $this->createRequirement(true),
            'shopware_account' => $this->createRequirement(true),
        ]));

        $app = $this->createApp(['service_consent', 'shopware_account'], 'pending_permissions');

        static::assertTrue($validator->isSatisfied($app));
    }

    public function testIsSatisfiedReturnsFalseWhenAnyNotMet(): void
    {
        $validator = new RequirementsValidator(new \ArrayIterator([
            'service_consent' => $this->createRequirement(true),
            'shopware_account' => $this->createRequirement(false),
        ]));

        $app = $this->createApp(['service_consent', 'shopware_account'], 'pending_permissions');

        static::assertFalse($validator->isSatisfied($app));
    }

    public function testUnknownRequirementBlocksActivationForInactiveService(): void
    {
        $validator = new RequirementsValidator(new \ArrayIterator([
            'service_consent' => $this->createRequirement(true),
        ]));

        $app = $this->createApp(['service_consent', 'unknown_requirement'], 'pending_permissions');

        static::assertFalse($validator->isSatisfied($app));
    }

    public function testUnknownRequirementIsIgnoredForActiveService(): void
    {
        $validator = new RequirementsValidator(new \ArrayIterator([
            'service_consent' => $this->createRequirement(true),
        ]));

        $app = $this->createApp(['service_consent', 'unknown_requirement'], 'active');

        static::assertTrue($validator->isSatisfied($app));
    }

    public function testKnownUnmetRequirementStillRevokesActiveService(): void
    {
        $validator = new RequirementsValidator(new \ArrayIterator([
            'service_consent' => $this->createRequirement(false),
        ]));

        $app = $this->createApp(['service_consent'], 'active');

        static::assertFalse($validator->isSatisfied($app));
    }

    /**
     * @param list<string> $requirements
     */
    private function createApp(array $requirements, string $state): AppEntity
    {
        $app = new AppEntity();
        $app->assign([
            'id' => 'app-' . bin2hex(random_bytes(4)),
            'name' => 'TestApp',
            'selfManaged' => true,
            'sourceConfig' => ['requirements' => $requirements],
        ]);

        // State is derived from requestedPrivileges + active:
        // ACTIVE = no requested privileges + active
        // PENDING_PERMISSIONS = has requested privileges + active
        if ($state === 'active') {
            $app->assign([
                'active' => true,
                'requestedPrivileges' => [],
            ]);
        } else {
            $app->assign([
                'active' => true,
                'requestedPrivileges' => ['some:privilege'],
            ]);
        }

        return $app;
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
                return '';
            }

            public function isSatisfied(): bool
            {
                return $this->satisfied;
            }
        };
    }
}
