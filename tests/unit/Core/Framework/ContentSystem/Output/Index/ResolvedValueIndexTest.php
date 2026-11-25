<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Output\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Output\Index\ResolvedValueIndex;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ResolvedValueIndex::class)]
class ResolvedValueIndexTest extends TestCase
{
    #[TestDox('hands both maps back unchanged')]
    public function testTheTwoMapsSurviveConstruction(): void
    {
        $index = new ResolvedValueIndex(
            ['r1' => 'Hello', 'r2' => null],
            ['element-1' => ['headline' => 'r1', 'product' => 'r2']]
        );

        static::assertSame(['r1' => 'Hello', 'r2' => null], $index->data());
        static::assertSame(['element-1' => ['headline' => 'r1', 'product' => 'r2']], $index->assignments());
    }

    /**
     * Three entries and a lookup of the middle one, so the ref selects the value rather than the lookup
     * happening to answer with the only entry there is.
     */
    #[TestDox('resolves a known ref to the value that ref carries')]
    public function testValueResolvesAKnownRef(): void
    {
        $index = new ResolvedValueIndex(['r1' => 'first', 'r2' => 'middle', 'r3' => 'last'], []);

        static::assertSame('middle', $index->value('r2'));
    }

    /**
     * A ref holding null is a loader that ran and found nothing, so the lookup has to answer null rather than
     * treat the ref as absent. This is the case a `??` implementation would get wrong.
     */
    #[TestDox('resolves a ref holding null instead of treating it as unknown')]
    public function testValueResolvesARefHoldingNull(): void
    {
        $index = new ResolvedValueIndex(['r1' => null], []);

        static::assertNull($index->value('r1'));
    }

    #[TestDox('value throws on a ref the index does not hold instead of answering null')]
    public function testValueThrowsOnAnUnknownRef(): void
    {
        $index = new ResolvedValueIndex(['r1' => 'Hello'], []);

        try {
            $index->value('r9');
            static::fail('Expected ContentSystemException was not thrown.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::INVALID_MAP_VALUE, $exception->getErrorCode());
            static::assertSame(
                'Resolved value index lookup value for "r9" must be a ref present in the index data, got no such ref',
                $exception->getMessage()
            );
        }
    }

    #[TestDox('construction throws when an assignment names a ref the data map does not hold')]
    public function testConstructionThrowsOnAnAssignmentNamingAMissingRef(): void
    {
        try {
            new ResolvedValueIndex(['r1' => 'Hello'], ['element-1' => ['headline' => 'r1', 'product' => 'r9']]);
            static::fail('Expected ContentSystemException was not thrown.');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::INVALID_MAP_VALUE, $exception->getErrorCode());
            static::assertSame(
                'Resolved value index assignment value for "element-1.product" must be a ref present in the index data, got unknown ref "r9"',
                $exception->getMessage()
            );
        }
    }
}
