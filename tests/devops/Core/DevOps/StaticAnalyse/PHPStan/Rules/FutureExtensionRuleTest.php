<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\PhpDoc\TypeStringResolver;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\FutureCompatibility\AnnouncedTypeResolver;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\FutureCompatibility\FutureExtensionRule;

/**
 * @internal
 *
 * @extends RuleTestCase<FutureExtensionRule>
 */
class FutureExtensionRuleTest extends RuleTestCase
{
    #[RunInSeparateProcess]
    public function testFutureIncompatibleExtensionsAreReported(): void
    {
        $this->analyse([__DIR__ . '/data/FutureExtensionRule/future_extenders.php'], [
            [
                '"Shopware\Core\DevOps\MyFakeNamespace\ExtendsFinal" extends "Shopware\Core\DevOps\MyFakeNamespace\WillBeFinal", which will become final in v6.8.0. There is no forward-compatible way to keep extending it.',
                52,
            ],
            [
                '"Shopware\Core\DevOps\MyFakeNamespace\ExtendsInternal" extends "Shopware\Core\DevOps\MyFakeNamespace\WillBeInternal", which will become internal in v6.8.0. Stop extending it to stay compatible.',
                56,
            ],
            [
                '"Shopware\Core\DevOps\MyFakeNamespace\ExtendsChangingHierarchy" extends "Shopware\Core\DevOps\MyFakeNamespace\HierarchyChanges", whose class hierarchy will change in v6.8.0: Will extend EntityCollection directly.',
                60,
            ],
            [
                '"ExtensionPointBase::toBeAbstract()" will become abstract in v6.8.0. Implement it in "Shopware\Core\DevOps\MyFakeNamespace\IncompatibleExtension" now to stay compatible with both versions.',
                64,
            ],
            [
                '"ExtensionPointBase::gainsParameter()" will get a new optional parameter $states (array) in v6.8.0. Add it to the override in "Shopware\Core\DevOps\MyFakeNamespace\IncompatibleExtension" now to stay compatible with both versions.',
                64,
            ],
            [
                'Parameter $value of "ExtensionPointBase::widensParameter()" will be widened to string|int in v6.8.0. Widen the override in "Shopware\Core\DevOps\MyFakeNamespace\IncompatibleExtension" now to stay compatible with both versions.',
                64,
            ],
            [
                'The return type of "ExtensionPointBase::narrowsReturn()" will be narrowed to string in v6.8.0. Narrow the override in "Shopware\Core\DevOps\MyFakeNamespace\IncompatibleExtension" now to stay compatible with both versions.',
                64,
            ],
        ]);
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [
            __DIR__ . '/data/FutureExtensionRule/scan.neon',
        ];
    }

    protected function getRule(): Rule
    {
        return new FutureExtensionRule(
            new AnnouncedTypeResolver(
                self::getContainer()->getByType(TypeStringResolver::class),
                self::createReflectionProvider()
            )
        );
    }
}
