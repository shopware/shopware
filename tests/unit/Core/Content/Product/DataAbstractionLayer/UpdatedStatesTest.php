<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\UpdatedStates;
use Shopware\Core\Framework\Feature;

/**
 * @internal
 */
#[CoversClass(UpdatedStates::class)]
class UpdatedStatesTest extends TestCase
{
    public function testUpdatedStates(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $updatedStates = new UpdatedStates('foobar', ['foo'], ['bar']);

        static::assertSame('foobar', $updatedStates->getId());
        static::assertSame(['foo'], $updatedStates->getOldStates());
        static::assertSame(['bar'], $updatedStates->getNewStates());

        $updatedStates->setNewStates(['foo']);

        static::assertSame(['foo'], $updatedStates->getNewStates());
    }
}
