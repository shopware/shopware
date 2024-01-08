<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\RuleConfig;

/**
 * @internal
 */
#[Package('business-ops')]
#[CoversClass(RuleConfig::class)]
#[Group('rules')]
class RuleConfigTest extends TestCase
{
    public function testNonExistentFieldReturnsNull(): void
    {
        $ruleConfig = new RuleConfig();

        static::assertNull($ruleConfig->getField('nonExistent'));
    }

    public function testFieldIsReturned(): void
    {
        $ruleConfig = new RuleConfig();

        $ruleConfig->field('foo', 'int', []);

        $field = $ruleConfig->getField('foo');

        static::assertNotNull($field);
        static::assertEquals('foo', $field['name']);
        static::assertEquals('int', $field['type']);
    }

    public function testFieldIsOverwritten(): void
    {
        $ruleConfig = new RuleConfig();

        $ruleConfig->field('foo', 'int', []);
        $ruleConfig->field('foo', 'string', []);

        $field = $ruleConfig->getField('foo');

        static::assertNotNull($field);
        static::assertEquals('foo', $field['name']);
        static::assertEquals('string', $field['type']);
    }
}
