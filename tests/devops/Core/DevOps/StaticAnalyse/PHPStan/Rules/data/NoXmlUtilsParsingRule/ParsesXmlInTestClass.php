<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoXmlUtilsParsingRule;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal
 */
class ParsesXmlInTestClass extends TestCase
{
    public function testParsesFixture(): void
    {
        static::assertNotNull(XmlUtils::loadFile(__DIR__ . '/fixture.xml')->documentElement);
    }
}
