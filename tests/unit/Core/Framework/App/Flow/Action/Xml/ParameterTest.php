<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Flow\Action\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Flow\Action\Xml\Parameter;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal
 */
#[CoversClass(Parameter::class)]
class ParameterTest extends TestCase
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
        $parameters = $action->getElementsByTagName('parameters')->item(0);
        static::assertNotNull($parameters);
        $parameter = $parameters->getElementsByTagName('parameter')->item(0);
        static::assertNotNull($parameter);

        $expected = [
            'type' => 'string',
            'name' => 'to',
            'value' => '{{ customer.name }}',
        ];

        $result = Parameter::fromXml($parameter);
        static::assertCount(3, $result->toArray('en-GB'));
        static::assertSame($expected, $result->toArray('en-GB'));
    }
}
