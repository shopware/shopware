<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use Shopware\Core\Framework\Log\Package;

/**
 * A new unit test class does not pull helpers in through a trait. Fixture builders belong in a static
 * fixture class (see `Shopware\Core\Test\Checkout\CartRuleFixture`), doubles of a class under test in a
 * `Stub/` folder next to the test, and a trait that boots the kernel does not belong in the unit suite at
 * all. The only traits a unit test class may use are the lifecycle behaviours that need the test instance
 * to restore state after the test. Applies to newly added tests only; existing consumers are migrated in
 * their own slices.
 *
 * Only `use` statements inside the test class itself count: a stub class declared further down in the
 * same file may still compose a production trait it exercises.
 *
 * @internal
 */
#[Package('framework')]
class TraitUsageInNewUnitTests
{
    /**
     * Behaviours that hook into the test lifecycle and therefore need the test instance. `EventDispatcherBehaviour`
     * is not one of them in the unit suite: a unit test owns its dispatcher, so it registers listeners on it directly.
     * `EnvTestBehaviour` is not either: `TestEnvironment::set()` writes the variables and the FeatureFlag extension
     * restores them after every unit test.
     */
    private const ALLOWED_TRAITS = [
        'Symfony\Component\Clock\Test\ClockSensitiveTrait',
    ];

    public function __invoke(Context $context): void
    {
        $violations = [];
        foreach ($context->platform->pullRequest->getFiles()->filterStatus(File::STATUS_ADDED)->matches('tests/unit/**/*Test.php') as $file) {
            // rule-test fixtures deliberately contain the pattern
            if (str_contains($file->name, '/data/')) {
                continue;
            }

            foreach ($this->traitsUsedByTestClass($file->getContent()) as $trait) {
                if (!\in_array($trait, self::ALLOWED_TRAITS, true)) {
                    $violations[] = \sprintf('`%s` uses `%s`', $file->name, $trait);
                }
            }
        }

        if ($violations !== []) {
            $context->failure(
                'A new unit test class does not pull helpers in through a trait. Put fixture builders into a static'
                . ' fixture class, doubles into a `Stub/` folder next to the test, and keep kernel-booting behaviours'
                . ' out of the unit suite. Only `ClockSensitiveTrait` may stay; environment variables go through `TestEnvironment::set()`:<br/>'
                . implode('<br/>', $violations)
            );
        }
    }

    /**
     * Fully qualified names of the traits composed into the first test class of the file.
     *
     * @return list<string>
     */
    private function traitsUsedByTestClass(string $content): array
    {
        if (!preg_match('/^namespace\s+([^;]+);/m', $content, $namespace)) {
            return [];
        }

        $imports = [];
        preg_match_all('/^use\s+([A-Za-z0-9_\\\\]+)(?:\s+as\s+(\w+))?;/m', $content, $importMatches, \PREG_SET_ORDER);
        foreach ($importMatches as $import) {
            $imports[$import[2] ?? substr($import[1], (int) strrpos($import[1], '\\') + 1)] = $import[1];
        }

        if (!preg_match('/^(?:final\s+|abstract\s+)?class\s+\w+Test\b[^{]*\{(.*?)^\}/ms', $content, $class)) {
            return [];
        }

        // a class-body use lists trait names and ends the statement; a closure `use (...)` never matches
        preg_match_all('/^\s{4}use\s+([A-Za-z0-9_\\\\]+(?:\s*,\s*[A-Za-z0-9_\\\\]+)*)\s*[;{]/m', $class[1], $uses);

        $traits = [];
        foreach ($uses[1] as $list) {
            foreach (preg_split('/\s*,\s*/', $list) ?: [] as $name) {
                $name = ltrim($name, '\\');
                $traits[] = $imports[$name] ?? (str_contains($name, '\\') ? $name : $namespace[1] . '\\' . $name);
            }
        }

        return $traits;
    }
}
