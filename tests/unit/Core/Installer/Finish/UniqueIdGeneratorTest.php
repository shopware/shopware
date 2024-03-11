<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Installer\Finish;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Installer\Finish\UniqueIdGenerator;

/**
 * @internal
 */
#[CoversClass(UniqueIdGenerator::class)]
class UniqueIdGeneratorTest extends TestCase
{
    protected function tearDown(): void
    {
        unlink(__DIR__ . '/.uniqueid.txt');
    }

    public function testGetUniqueId(): void
    {
        $idGenerator = new UniqueIdGenerator(__DIR__);
        $id = $idGenerator->getUniqueId();

        // assert that the generated id is the same on multiple calls
        static::assertEquals($id, $idGenerator->getUniqueId());

        unlink(__DIR__ . '/.uniqueid.txt');

        // assert that the generated id is different on a new call
        static::assertNotEquals($id, $idGenerator->getUniqueId());
    }
}
