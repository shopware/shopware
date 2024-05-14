<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Flow\Action\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Flow\Action\Xml\Config;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal
 */
#[CoversClass(Config::class)]
class ConfigTest extends TestCase
{
    public function testFromXml(): void
    {
        $document = XmlUtils::loadFile(
            __DIR__ . '/../../../_fixtures/Resources/flow.xml',
            __DIR__ . '/../../../../../../../../src/Core/Framework/App/Flow/Schema/flow-1.0.xsd'
        );
        $actions = $document->getElementsByTagName('flow-actions')->item(0);
        static::assertNotNull($actions);
        $action = $actions->getElementsByTagName('flow-action')->item(0);
        static::assertNotNull($action);
        $config = $action->getElementsByTagName('config')->item(0);
        static::assertNotNull($config);

        $config = Config::fromXml($config);
        static::assertCount(4, $config->getConfig());
        static::assertSame('text', $config->getConfig()[0]->getType());
        static::assertSame('text', $config->getConfig()[1]->getType());
        static::assertSame('text', $config->getConfig()[2]->getType());
        static::assertSame('single-select', $config->getConfig()[3]->getType());
    }
}
