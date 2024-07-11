<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Framework\Search;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Framework\Search\CriteriaCollection;
use Shopware\Administration\Notification\NotificationEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * @internal
 */
#[CoversClass(CriteriaCollection::class)]
class CriteriaCollectionTest extends TestCase
{
    public function testGetExpectedClass(): void
    {
        $collection = new CriteriaCollection();

        $collection->add(new Criteria());


        static::expectException(\InvalidArgumentException::class);
        static::expectExceptionMessage(sprintf('Expected collection element of type %s got %s', Criteria::class, NotificationEntity::class));
        /** @phpstan-ignore-next-line intentionally wrong parameter provided **/
        $collection->add(new NotificationEntity());

        static::assertCount(1, $collection);
    }
}
