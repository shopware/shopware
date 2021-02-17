<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;

trait RuleTestBehaviour
{
    /**
     * @before
     */
    public function clearCachedRules(): void
    {
        $evaluator = $this->getContainer()->get(CartRuleLoader::class);
        $rulesProperty = ReflectionHelper::getProperty(CartRuleLoader::class, 'rules');
        $rulesProperty->setValue($evaluator, null);
    }
}
