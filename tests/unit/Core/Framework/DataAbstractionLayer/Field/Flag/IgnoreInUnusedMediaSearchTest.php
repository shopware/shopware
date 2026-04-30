<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Field\Flag;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\IgnoreInUnusedMediaSearch;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(IgnoreInUnusedMediaSearch::class)]
#[Package('framework')]
class IgnoreInUnusedMediaSearchTest extends TestCase
{
    public function testParse(): void
    {
        $flag = new IgnoreInUnusedMediaSearch();

        static::assertSame(['ignore_in_unused_media_search' => true], iterator_to_array($flag->parse()));
    }
}
