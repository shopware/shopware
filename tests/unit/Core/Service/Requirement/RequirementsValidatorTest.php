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

        $app = $this->createApp(['service_consent', 'shopware_account'], false);

        static::assertTrue($validator->isSatisfied($app));
    }

    public function testIsSatisfiedReturnsFalseWhenAnyNotMet(): void
    {
        $validator = new RequirementsValidator(new \ArrayIterator([
            'service_consent' => $this->createRequirement(true),
            'shopware_account' => $this->createRequirement(false),
        ]));

        $app = $this->createApp(['service_consent', 'shopware_account'], false);

        static::assertFalse($validator->isSatisfied($app));
    }

    public function testUnknownRequirementBlocksActivationForInactiveService(): void
    {
        $validator = new RequirementsValidator(new \ArrayIterator([
            'service_consent' => $this->createRequirement(true),
        ]));

        $app = $this->createApp(['service_consent', 'unknown_requirement'], false);

        static::assertFalse($validator->isSatisfied($app));
    }

    public function testUnknownRequirementIsIgnoredForActiveService(): void
    {
        $validator = new RequirementsValidator(new \ArrayIterator([
            'service_consent' => $this->createRequirement(true),
        ]));

        $app = $this->createApp(['service_consent', 'unknown_requirement'], true);

        static::assertTrue($validator->isSatisfied($app));
    }

    public function testKnownUnmetRequirementStillRevokesActiveService(): void
    {
        $validator = new RequirementsValidator(new \ArrayIterator([
            'service_consent' => $this->createRequirement(false),
        ]));

        $app = $this->createApp(['service_consent'], true);

        static::assertFalse($validator->isSatisfied($app));
    }

    /**
     * @param list<string> $requirements
     */
    private function createApp(array $requirements, bool $active = true): AppEntity
    {
        $app = new AppEntity();
        $app->assign([
            'id' => 'app-' . bin2hex(random_bytes(4)),
            'name' => 'TestApp',
            'selfManaged' => true,
            'sourceConfig' => ['requirements' => $requirements],
            'active' => true,
            'requestedPrivileges' => $active ? [] : ['some:privilege'],
        ]);

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
                return 'test';
            }

            public function isSatisfied(): bool
            {
                return $this->satisfied;
            }
        };
    }
}
