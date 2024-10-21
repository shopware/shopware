<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cms\DataResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\Exception\UnexpectedFieldConfigValueType;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(FieldConfig::class)]
class FieldConfigTest extends TestCase
{
    public function testFieldConfig(): void
    {
        $config = new FieldConfig('my-config', 'static', ['some-value']);

        static::assertSame('my-config', $config->getName());
        static::assertSame('static', $config->getSource());
        static::assertSame(['some-value'], $config->getValue());
        static::assertSame(['some-value'], $config->getArrayValue());
        static::assertTrue($config->getBoolValue());
        static::assertTrue($config->isStatic());
        static::assertFalse($config->isMapped());
        static::assertFalse($config->isDefault());
        static::assertFalse($config->isProductStream());
        static::assertSame('cms_data_resolver_field_config', $config->getApiAlias());
    }

    public function testFieldConfigCastsTheValues(): void
    {
        $config = new FieldConfig('my-config', 'static', '3');
        static::assertSame(3, $config->getIntValue());
        static::assertSame('3', $config->getStringValue());
        static::assertSame(3.0, $config->getFloatValue());
    }

    public function testThrowExceptionOnGetArrayValue(): void
    {
        static::expectException(UnexpectedFieldConfigValueType::class);
        static::expectExceptionMessage('Expected to load value of "my-config" with type "array", but value with type "string" given.');
        (new FieldConfig('my-config', 'static', 'some-value'))->getArrayValue();
    }

    public function testThrowExceptionOnGetIntValue(): void
    {
        static::expectException(UnexpectedFieldConfigValueType::class);
        static::expectExceptionMessage('Expected to load value of "my-config" with type "int", but value with type "array" given.');
        (new FieldConfig('my-config', 'static', ['some-value']))->getIntValue();
    }

    public function testThrowExceptionOnGetFloatValue(): void
    {
        static::expectException(UnexpectedFieldConfigValueType::class);
        static::expectExceptionMessage('Expected to load value of "my-config" with type "float", but value with type "array" given.');
        (new FieldConfig('my-config', 'static', ['some-value']))->getFloatValue();
    }

    public function testThrowExceptionOnGetStringValue(): void
    {
        static::expectException(UnexpectedFieldConfigValueType::class);
        static::expectExceptionMessage('Expected to load value of "my-config" with type "string", but value with type "array" given.');
        (new FieldConfig('my-config', 'static', ['some-value']))->getStringValue();
    }
}
