<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\PhpDoc\TypeStringResolver;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\FutureCompatibility\AnnouncedTypeResolver;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\FutureCompatibility\FutureCallSiteRule;

/**
 * @internal
 *
 * @extends RuleTestCase<FutureCallSiteRule>
 */
class FutureCallSiteRuleTest extends RuleTestCase
{
    #[RunInSeparateProcess]
    public function testFutureIncompatibleCallSitesAreReported(): void
    {
        $this->analyse([__DIR__ . '/data/FutureCallSiteRule/future_call_sites.php'], [
            [
                '"Shopware\Core\DevOps\MyFakeNamespace\BCSubject::internalMethod()" will become internal in v6.8.0. Stop calling it to stay compatible.',
                51,
            ],
            [
                '"Shopware\Core\DevOps\MyFakeNamespace\BCSubject::becomesProtected()" will become protected in v6.8.0. This call will break; stop calling it from outside that scope.',
                52,
            ],
            [
                'Parameter $options of "Shopware\Core\DevOps\MyFakeNamespace\BCSubject::withRemoval()" will be removed in v6.8.0. Stop passing it to stay compatible with both versions.',
                53,
            ],
            [
                'Parameter $options of "Shopware\Core\DevOps\MyFakeNamespace\BCSubject::withRemoval()" will be removed in v6.8.0. Stop passing it to stay compatible with both versions.',
                54,
            ],
            [
                'Parameter $oldName of "Shopware\Core\DevOps\MyFakeNamespace\BCSubject::withRename()" will be renamed to $newName in v6.8.0. A named argument cannot be compatible with both versions; pass it positionally.',
                57,
            ],
            [
                'Parameter $id of "Shopware\Core\DevOps\MyFakeNamespace\BCSubject::withNarrowing()" will be narrowed to string in v6.8.0, but int is passed. Pass string to stay compatible with both versions.',
                59,
            ],
            [
                'Class "Shopware\Core\DevOps\MyFakeNamespace\InternalSubject" will become internal in v6.8.0. Stop using it to stay compatible.',
                60,
            ],
            [
                'Class "Shopware\Core\DevOps\MyFakeNamespace\InternalSubject" will become internal in v6.8.0. Stop using it to stay compatible.',
                61,
            ],
        ]);
    }

    public static function getAdditionalConfigFiles(): array
    {
        return [
            __DIR__ . '/data/FutureCallSiteRule/scan.neon',
        ];
    }

    protected function getRule(): Rule
    {
        return new FutureCallSiteRule(
            self::createReflectionProvider(),
            new AnnouncedTypeResolver(
                self::getContainer()->getByType(TypeStringResolver::class),
                self::createReflectionProvider()
            )
        );
    }
}
