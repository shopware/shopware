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

        $app = $this->createApp(['service_consent', 'shopware_account']);

        static::assertTrue($validator->isSatisfied($app));
    }

    public function testIsSatisfiedReturnsFalseWhenAnyNotMet(): void
    {
        $validator = new RequirementsValidator(new \ArrayIterator([
            'service_consent' => $this->createRequirement(true),
            'shopware_account' => $this->createRequirement(false),
        ]));

        $app = $this->createApp(['service_consent', 'shopware_account']);

        static::assertFalse($validator->isSatisfied($app));
    }

    public function testIsSatisfiedReturnsFalseForUnknown(): void
    {
        $validator = new RequirementsValidator(new \ArrayIterator([
            'service_consent' => $this->createRequirement(true),
        ]));

        $app = $this->createApp(['service_consent', 'unknown_requirement']);

        static::assertFalse($validator->isSatisfied($app));
    }

    /**
     * @param list<string> $requirements
     */
    private function createApp(array $requirements): AppEntity
    {
        $sourceConfig = [
            'version' => '1.0.0',
            'hash' => 'a453f',
            'revision' => '1.0.0-a453f',
            'zip-url' => 'https://example.com/zip',
            'hash-algorithm' => 'sha256',
            'min-shop-supported-version' => '6.6.0.0',
            'requirements' => $requirements,
        ];

        $app = new AppEntity();
        $app->assign([
            'id' => 'app-' . bin2hex(random_bytes(4)),
            'name' => 'TestApp',
            'selfManaged' => true,
            'sourceConfig' => $sourceConfig,
            'active' => true,
            'requestedPrivileges' => ['some:privilege'],
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
