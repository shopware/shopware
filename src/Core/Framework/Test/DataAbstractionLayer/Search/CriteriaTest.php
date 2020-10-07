<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class CriteriaTest extends TestCase
{
    public function testMissingConstructorParameterSetsEmptyIds(): void
    {
        $criteria = new Criteria();

        static::assertEquals([], $criteria->getIds());
    }

    public function testNullConstructorParameterSetsEmptyIds(): void
    {
        $criteria = new Criteria(null);

        static::assertEquals([], $criteria->getIds());
    }

    public function testInconsistentIdsExceptionIsThrown(): void
    {
        $this->expectException(InconsistentCriteriaIdsException::class);

        new Criteria([
            null,
            'foo',
        ]);
    }

    public function testEmptyArrayThrowsException(): void
    {
        $this->expectException(InconsistentCriteriaIdsException::class);

        new Criteria([]);
    }

    public function testNullInCloneForReadSetsEmptyIds(): void
    {
        $criteria = new Criteria();

        $cloned = $criteria->cloneForRead(null);

        static::assertEquals([], $cloned->getIds());
    }

    public function testInconsistentIdsInCloneForReadExceptionIsThrown(): void
    {
        $this->expectException(InconsistentCriteriaIdsException::class);

        (new Criteria())->cloneForRead([
            null,
            'foo',
        ]);
    }

    public function testCloneFromWithEmptyArrayThrowsException(): void
    {
        $this->expectException(InconsistentCriteriaIdsException::class);

        (new Criteria())->cloneForRead([]);
    }
}
