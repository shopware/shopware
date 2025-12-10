<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Consent;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Consent\ConsentContext;
use Shopware\Core\System\Consent\ConsentScope;

/**
 * @internal
 */
#[CoversClass(ConsentContext::class)]
class ConsentContextTest extends TestCase
{
    public function testAddAndGetIdentifierForScope(): void
    {
        $context = new ConsentContext();
        $id = Uuid::randomHex();
        $result = $context->add(ConsentScope::ADMIN_USER, $id);

        static::assertSame($id, $result->getIdentifierForScope(ConsentScope::ADMIN_USER));
    }

    public function testGetIdentifierForScopeReturnsNullForNonExistentScope(): void
    {
        $context = new ConsentContext();

        static::assertNull($context->getIdentifierForScope(ConsentScope::GLOBAL));
    }
}
