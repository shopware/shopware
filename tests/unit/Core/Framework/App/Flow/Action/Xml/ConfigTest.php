<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Flow\Action\Xml;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Flow\Action\Xml\Config;
use Shopware\Core\Framework\App\Flow\Action\Xml\InputField;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\App\Flow\Action\Xml\Config
 */
class ConfigTest extends TestCase
{
    public function testFromXml(): void
    {
        $document = XmlUtils::loadFile(
            __DIR__ . '/../../../_fixtures/Resources/flow-action.xml',
            __DIR__ . '/../../../../../../../../src/Core/Framework/App/FlowAction/Schema/flow-action-1.0.xsd'
        );
        $actions = $document->getElementsByTagName('flow-actions')->item(0);
        static::assertNotNull($actions);
        $action = $actions->getElementsByTagName('flow-action')->item(0);
        static::assertNotNull($action);
        $config = $action->getElementsByTagName('config')->item(0);
        static::assertNotNull($config);

        $config = Config::fromXml($config);
        static::assertCount(4, $config->getConfig());
        static::assertInstanceOf(InputField::class, $config->getConfig()[0]);
        static::assertInstanceOf(InputField::class, $config->getConfig()[1]);
        static::assertInstanceOf(InputField::class, $config->getConfig()[2]);
        static::assertInstanceOf(InputField::class, $config->getConfig()[3]);
    }
}
