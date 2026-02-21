<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Hydration\DataContext;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Hydration\DataContext\ContextType;

/**
 * @internal
 */
#[CoversClass(ContextType::class)]
class ContextTypeTest extends TestCase
{
    #[TestDox('returns all case values as list of strings')]
    public function testValuesReturnsAllCaseValues(): void
    {
        $values = ContextType::values();

        static::assertSame(['single', 'collection'], $values);
    }
}
