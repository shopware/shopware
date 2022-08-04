<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\OpenApi\Event;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\Event\OpenApiPathsEvent;

/**
 * @covers \Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi\Event\OpenApiPathsEvent
 *
 * @internal
 */
class OpenApiPathsEventTest extends TestCase
{
    public function testPathsAreEmtpy(): void
    {
        $event = new OpenApiPathsEvent([]);
        static::assertEmpty($event->getPaths());
        static::assertTrue($event->isEmpty());
    }

    public function testPathsAreNotEmpty(): void
    {
        $event = new OpenApiPathsEvent([]);

        $event->addPath('/foo/testController.php');

        static::assertCount(1, $event->getPaths());
        static::assertFalse($event->isEmpty());
    }
}
