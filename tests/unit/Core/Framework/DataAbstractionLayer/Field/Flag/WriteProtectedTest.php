<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Field\Flag;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WriteProtected::class)]
class WriteProtectedTest extends TestCase
{
    public function testAllowsWriteThroughAdminApiWithoutExposingWriteProtectionInEntitySchema(): void
    {
        $flag = (new WriteProtected(Context::SYSTEM_SCOPE))->allowWriteThroughAdminApi();

        static::assertTrue($flag->isAllowed(Context::SYSTEM_SCOPE));
        static::assertSame([], iterator_to_array($flag->parse()));
    }
}
