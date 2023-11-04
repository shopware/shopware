<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Flow\Event;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Flow\Event\Event;
use Shopware\Core\System\SystemConfig\Exception\XmlParsingException;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\App\Flow\Event\Event
 */
class FlowEventTest extends TestCase
{
    public function testCreateFromXmlFile(): void
    {
        $xmlFile = \dirname(__FILE__, 3) . '/_fixtures/Resources/flow.xml';
        $result = Event::createFromXmlFile($xmlFile);
        static::assertNotNull($result->getCustomEvents());
        static::assertNotEmpty($result->getCustomEvents()->getCustomEvents());
        static::assertDirectoryExists($result->getPath());
    }

    public function testCreateFromXmlFileFaild(): void
    {
        static::expectException(XmlParsingException::class);
        $xmlFile = \dirname(__FILE__, 3) . '/_fixtures/flow-1-0.xml';
        Event::createFromXmlFile($xmlFile);
    }
}
