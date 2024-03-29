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
#[Package('buyers-experience')]
#[CoversClass(SalutationSorter::class)]
class SalutationSorterTest extends TestCase
{
    public function testSort(): void
    {
        $mrs = new SalutationEntity();
        $mrs->setId(Uuid::randomBytes());
        $mrs->setSalutationKey('mrs');

        $mr = new SalutationEntity();
        $mr->setId(Uuid::randomBytes());
        $mr->setSalutationKey('mr');

        $notSpecified = new SalutationEntity();
        $notSpecified->setId(Uuid::randomBytes());
        $notSpecified->setSalutationKey('not_specified');

        $test = new SalutationEntity();
        $test->setId(Uuid::randomHex());
        $test->setSalutationKey('test');

        $salutations = new SalutationCollection();
        $salutations->add($mr);
        $salutations->add($mrs);
        $salutations->add($notSpecified);
        $salutations->add($test);

        static::assertSame($salutations->first(), $mr);

        $sorter = new SalutationSorter();
        $salutations = $sorter->sort($salutations);

        static::assertSame($salutations->first(), $notSpecified);
    }
}
