<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Flow\Action\Xml;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Flow\Action\Xml\Parameter;
use Shopware\Core\Framework\App\Flow\Action\Xml\Parameters;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\App\Flow\Action\Xml\Parameters
 */
class ParametersTest extends TestCase
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
        $parameters = $action->getElementsByTagName('parameters')->item(0);
        static::assertNotNull($parameters);

        $result = Parameters::fromXml($parameters)->getParameters();
        static::assertCount(3, $result);
        static::assertInstanceOf(Parameter::class, $result[0]);
        static::assertInstanceOf(Parameter::class, $result[1]);
        static::assertInstanceOf(Parameter::class, $result[2]);
    }
}
