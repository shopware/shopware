<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\AndRule;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(RuleEntity::class)]
class RuleEntityTest extends TestCase
{
    protected function tearDown(): void
    {
        FieldVisibility::$isInTwigRenderingContext = false;
    }

    public function testPayloadIsReadableOutsideTwig(): void
    {
        $payload = new AndRule();
        $rule = $this->ruleWithInternalPayload();
        $rule->setPayload($payload);

        static::assertSame($payload, $rule->getPayload());
    }

    public function testPayloadIsGuardedInsideTwig(): void
    {
        $rule = $this->ruleWithInternalPayload();
        $rule->setPayload(new AndRule());

        FieldVisibility::$isInTwigRenderingContext = true;

        $this->expectExceptionObject(DataAbstractionLayerException::internalFieldAccessNotAllowed('payload', RuleEntity::class));
        $rule->getPayload();
    }

    private function ruleWithInternalPayload(): RuleEntity
    {
        $rule = new RuleEntity();
        $rule->internalSetEntityData(RuleDefinition::ENTITY_NAME, new FieldVisibility(['payload']));

        return $rule;
    }
}
