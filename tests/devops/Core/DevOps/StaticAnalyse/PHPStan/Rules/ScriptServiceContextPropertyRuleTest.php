<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\ScriptServiceContextPropertyRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<ScriptServiceContextPropertyRule>
 */
#[Package('framework')]
class ScriptServiceContextPropertyRuleTest extends RuleTestCase
{
    public function testScriptServiceKeepingAContextIsReported(): void
    {
        $class = 'Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\ScriptServiceContextPropertyRule\ScriptServiceWithContext';

        $this->analyse([__DIR__ . '/data/ScriptServiceContextPropertyRule/ScriptServiceWithContext.php'], [
            [\sprintf(ScriptServiceContextPropertyRule::ERROR_MESSAGE, $class, 'lastContext'), 12, ScriptServiceContextPropertyRule::ERROR_TIP],
            [\sprintf(ScriptServiceContextPropertyRule::ERROR_MESSAGE, $class, 'context'), 15, ScriptServiceContextPropertyRule::ERROR_TIP],
        ]);
    }

    public function testScriptServiceUsingTheContextFactoryIsNotReported(): void
    {
        $this->analyse([__DIR__ . '/data/ScriptServiceContextPropertyRule/ScriptServiceWithFactory.php'], []);
    }

    public function testClassWithoutScriptServiceAnnotationIsNotReported(): void
    {
        $this->analyse([__DIR__ . '/data/ScriptServiceContextPropertyRule/ServiceWithContext.php'], []);
    }

    public function testAllowlistedAclFacadeIsNotReported(): void
    {
        $this->analyse([__DIR__ . '/../../../../../../../src/Core/Framework/Script/Api/AclFacade.php'], []);
    }

    protected function getRule(): Rule
    {
        return new ScriptServiceContextPropertyRule();
    }
}
