<?php declare(strict_types=1);

use Shopware\Core\Checkout\Test\Cart\Common\FalseRule;
use Shopware\Core\Checkout\Test\Cart\Common\TrueRule;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Rule\Container\AndRule;

$ruleCollection = new RuleCollection();

$rule = new RuleEntity();
$rule->setId('f343a3c119cf42a7841aa0ac5094908c');
$rule->setName('Test rule');
$rule->setDescription('Test description');
$rule->setPayload(new AndRule([new TrueRule(), new FalseRule()]));
$rule->setViewData(new RuleEntity());
$ruleCollection->add($rule);

return $ruleCollection;
