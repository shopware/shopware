<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Flow\Action\Xml;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Flow\Action\Xml\Headers;
use Shopware\Core\Framework\App\Flow\Action\Xml\Parameter;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\App\Flow\Action\Xml\Headers
 */
class HeadersTest extends TestCase
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

        /** @var \DOMElement $headers */
        $headers = $action->getElementsByTagName('headers')->item(0);

        $headers = Headers::fromXml($headers);
        static::assertCount(2, $headers->getParameters());
        static::assertInstanceOf(Parameter::class, $headers->getParameters()[0]);
        static::assertInstanceOf(Parameter::class, $headers->getParameters()[1]);
    }
}
