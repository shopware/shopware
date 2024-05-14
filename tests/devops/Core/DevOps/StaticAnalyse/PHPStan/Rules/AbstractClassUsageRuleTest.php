<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\AbstractClassUsageRule;

/**
 * @internal
 *
 * @extends  RuleTestCase<AbstractClassUsageRule>
 */
#[CoversClass(AbstractClassUsageRule::class)]
class AbstractClassUsageRuleTest extends RuleTestCase
{
    #[RunInSeparateProcess]
    public function testRule(): void
    {
        // not in a class, ignore
        $this->analyse([__DIR__ . '/data/AbstractClassUsageRule/not-in-class.php'], []);

        // class has no constructor, ignore
        $this->analyse([__DIR__ . '/data/AbstractClassUsageRule/no-constructor.php'], []);

        // context has no decoration pattern, ignore
        $this->analyse([__DIR__ . '/data/AbstractClassUsageRule/no-decoration-pattern.php'], []);

        // abstract class correctly used, ignore
        $this->analyse([__DIR__ . '/data/AbstractClassUsageRule/abstract-used.php'], []);

        // skipped implementation used, ignore
        $this->analyse([__DIR__ . '/data/AbstractClassUsageRule/skipped-implementation-used.php'], []);

        // not in namespace, autoload is passed as true, error
        $this->analyse([__DIR__ . '/data/AbstractClassUsageRule/implementation-used.php'], [
            [
                'Decoration error: Parameter Shopware\Core\Framework\Adapter\Translation\Translator $translator of Service is using the decoration pattern, but non-abstract constructor parameter is used.',
                8,
            ],
        ]);
    }

    /**
     * @return AbstractClassUsageRule
     */
    protected function getRule(): Rule
    {
        return new AbstractClassUsageRule();
    }
}
