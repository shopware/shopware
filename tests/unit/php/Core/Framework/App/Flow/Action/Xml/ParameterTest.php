<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Flow\Action\Xml;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Flow\Action\Xml\Parameter;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\App\Flow\Action\Xml\Parameter
 */
class ParameterTest extends TestCase
{
    public function testFromXml(): void
    {
        $document = XmlUtils::loadFile(
            __DIR__ . '/../../../_fixtures/Resources/flow-action.xml',
            __DIR__ . '/../../../../../../../../../src/Core/Framework/App/FlowAction/Schema/flow-action-1.0.xsd'
        );
        /** @var \DOMElement $actions */
        $actions = $document->getElementsByTagName('flow-actions')->item(0);
        /** @var \DOMElement $action */
        $action = $actions->getElementsByTagName('flow-action')->item(0);

        /** @var \DOMElement $parameters */
        $parameters = $action->getElementsByTagName('parameters')->item(0);

        /** @var \DOMElement $parameter */
        $parameter = $parameters->getElementsByTagName('parameter')->item(0);

        $expected = [
            'type' => 'string',
            'name' => 'to',
            'value' => '{{ customer.name }}',
        ];

        $result = Parameter::fromXml($parameter);
        static::assertCount(3, $result->toArray('en-GB'));
        static::assertEquals($expected, $result->toArray('en-GB'));
    }
}
