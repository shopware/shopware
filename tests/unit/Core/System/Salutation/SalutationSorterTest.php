<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Salutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Core\System\Salutation\SalutationEntity;
use Shopware\Core\System\Salutation\SalutationSorter;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(SalutationSorter::class)]
class SalutationSorterTest extends TestCase
{
    public function testSortByPosition(): void
    {
        $mrs = new SalutationEntity();
        $mrs->setId(Uuid::randomBytes());
        $mrs->setSalutationKey('mrs');
        $mrs->setPosition(3);

        $mr = new SalutationEntity();
        $mr->setId(Uuid::randomBytes());
        $mr->setSalutationKey('mr');
        $mr->setPosition(2);

        $notSpecified = new SalutationEntity();
        $notSpecified->setId(Uuid::randomBytes());
        $notSpecified->setSalutationKey('not_specified');
        $notSpecified->setPosition(0);

        $test = new SalutationEntity();
        $test->setId(Uuid::randomBytes());
        $test->setSalutationKey('test');
        $test->setPosition(1);

        $salutations = new SalutationCollection();
        $salutations->add($mr);
        $salutations->add($mrs);
        $salutations->add($notSpecified);
        $salutations->add($test);

        static::assertSame($salutations->first(), $mr);

        $sorter = new SalutationSorter();
        $salutations = $sorter->sort($salutations);

        static::assertSame(
            ['not_specified', 'test', 'mr', 'mrs'],
            \array_values(\array_map(
                static fn (SalutationEntity $salutation): string => (string) $salutation->getSalutationKey(),
                \iterator_to_array($salutations)
            ))
        );
    }
}
