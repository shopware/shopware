<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Aggregate\AppScriptCondition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionCollection;
use Shopware\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionEntity;
use Shopware\Core\Framework\App\Aggregate\AppScriptConditionTranslation\AppScriptConditionTranslationCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppScriptConditionEntity::class)]
class AppScriptConditionEntityTest extends TestCase
{
    public function testConstraintsRoundTrip(): void
    {
        $entity = new AppScriptConditionEntity();
        $entity->setConstraints(['operator' => []]);

        static::assertSame(['operator' => []], $entity->getConstraints());
    }

    public function testAccessorsRoundTrip(): void
    {
        $entity = new AppScriptConditionEntity();

        $app = new AppEntity();
        $ruleConditions = new RuleConditionCollection();
        $translations = new AppScriptConditionTranslationCollection();

        $entity->setAppId('app-id');
        $entity->setApp($app);
        $entity->setIdentifier('app_condition');
        $entity->setName('Condition');
        $entity->setActive(true);
        $entity->setGroup('general');
        $entity->setScript('return true;');
        $config = [
            'name' => 'operator',
            'config' => ['label' => ['en-GB' => 'Operator'], 'helpText' => ['en-GB' => 'Pick an operator'], 'customFieldPosition' => 1],
        ];
        $entity->setConfig($config);
        $entity->setRuleConditions($ruleConditions);
        $entity->setTranslations($translations);

        static::assertSame('app-id', $entity->getAppId());
        static::assertSame($app, $entity->getApp());
        static::assertSame('app_condition', $entity->getIdentifier());
        static::assertSame('Condition', $entity->getName());
        static::assertTrue($entity->isActive());
        static::assertSame('general', $entity->getGroup());
        static::assertSame('return true;', $entity->getScript());
        static::assertSame($config, $entity->getConfig());
        static::assertSame($ruleConditions, $entity->getRuleConditions());
        static::assertSame($translations, $entity->getTranslations());
    }
}
