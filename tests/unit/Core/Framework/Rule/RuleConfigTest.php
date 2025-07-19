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
#[Package('fundamentals@after-sales')]
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
        static::assertSame('foo', $field['name']);
        static::assertSame('int', $field['type']);
    }

    public function testFieldIsOverwritten(): void
    {
        $ruleConfig = new RuleConfig();

        $ruleConfig->field('foo', 'int', []);
        $ruleConfig->field('foo', 'string', []);

        $field = $ruleConfig->getField('foo');

        static::assertNotNull($field);
        static::assertSame('foo', $field['name']);
        static::assertSame('string', $field['type']);
    }

    public function testNumberFieldDefaultDigits(): void
    {
        $ruleConfig = new RuleConfig();

        $ruleConfig->numberField('foo', []);

        $field = $ruleConfig->getField('foo');

        static::assertNotNull($field);
        static::assertSame('foo', $field['name']);
        static::assertSame('float', $field['type']);
        static::assertSame(RuleConfig::DEFAULT_DIGITS, $field['config']['digits']);
    }

    public function testNotOverrideNumberFieldDigits(): void
    {
        $ruleConfig = new RuleConfig();

        $ruleConfig->numberField('foo', [
            'digits' => 5,
        ]);

        $field = $ruleConfig->getField('foo');

        static::assertNotNull($field);
        static::assertSame('foo', $field['name']);
        static::assertSame('float', $field['type']);
        static::assertSame(5, $field['config']['digits']);
    }

    public function testDateFieldConfig(): void
    {
        $ruleConfig = new RuleConfig();

        $ruleConfig->dateField('foo', [
            'someConfig' => 'bar',
        ]);

        $field = $ruleConfig->getField('foo');

        static::assertNotNull($field);
        static::assertSame('foo', $field['name']);
        static::assertSame('date', $field['type']);
        static::assertSame('bar', $field['config']['someConfig']);
    }

    public function testDateTimeFieldConfig(): void
    {
        $ruleConfig = new RuleConfig();

        $ruleConfig->dateTimeField('foo', [
            'someConfig' => 'bar',
        ]);

        $field = $ruleConfig->getField('foo');

        static::assertNotNull($field);
        static::assertSame('foo', $field['name']);
        static::assertSame('datetime', $field['type']);
        static::assertSame('bar', $field['config']['someConfig']);
    }
}
