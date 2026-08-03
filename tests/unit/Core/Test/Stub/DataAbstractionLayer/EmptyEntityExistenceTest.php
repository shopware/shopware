<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\Stub\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\EmptyEntityExistence;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EmptyEntityExistence::class)]
class EmptyEntityExistenceTest extends TestCase
{
    public function testICanCreateStub(): void
    {
        $stub = new EmptyEntityExistence();
        static::assertEmpty($stub->getEntityName());
    }
}
