<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Flow\Action\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Flow\Action\Xml\Headers;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal
 */
#[CoversClass(Headers::class)]
class HeadersTest extends TestCase
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
        $headers = $action->getElementsByTagName('headers')->item(0);
        static::assertNotNull($headers);

        $headers = Headers::fromXml($headers);
        static::assertCount(2, $headers->getParameters());
        static::assertSame('string', $headers->getParameters()[0]->getType());
        static::assertSame('string', $headers->getParameters()[1]->getType());
    }
}
