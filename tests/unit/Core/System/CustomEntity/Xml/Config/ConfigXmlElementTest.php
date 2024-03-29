<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomEntity\Xml\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\CustomEntity\Xml\Config\ConfigXmlElement;
use Shopware\Tests\Unit\Core\System\CustomEntity\Xml\Config\Fixture\TestElement;

/**
 * @package content
 *
 * @internal
 */
#[CoversClass(ConfigXmlElement::class)]
class ConfigXmlElementTest extends TestCase
{
    public function testJsonSerialize(): void
    {
        $extendedConfigXmlElement = TestElement::fromArray([]);

        $serializeResult = $extendedConfigXmlElement->jsonSerialize();
        static::assertSame(['testData' => 'TEST_DATA'], $serializeResult);

        static::assertSame([], $extendedConfigXmlElement->extensions);
        static::assertSame('TEST_DATA', $extendedConfigXmlElement->testData);
    }
}
