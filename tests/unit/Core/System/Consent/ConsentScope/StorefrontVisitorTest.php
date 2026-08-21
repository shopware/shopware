<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Consent\ConsentScope;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Consent\ConsentException;
use Shopware\Core\System\Consent\ConsentScope\StorefrontVisitor;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(StorefrontVisitor::class)]
class StorefrontVisitorTest extends TestCase
{
    private StorefrontVisitor $scope;

    protected function setUp(): void
    {
        $this->scope = new StorefrontVisitor();
    }

    public function testGetName(): void
    {
        static::assertSame('storefront_visitor', $this->scope->getName());
    }

    public function testAppliesToSalesChannelContextsOnly(): void
    {
        static::assertTrue($this->scope->appliesTo(new Context(new SalesChannelApiSource(Uuid::randomHex()))));
        static::assertFalse($this->scope->appliesTo(new Context(new AdminApiSource(Uuid::randomHex()))));
        static::assertFalse($this->scope->appliesTo(new Context(new SystemSource())));
    }

    public function testResolveIdentifierForSalesChannelContextIsAnonymous(): void
    {
        $context = new Context(new SalesChannelApiSource(Uuid::randomHex()));

        static::assertSame(StorefrontVisitor::IDENTIFIER, $this->scope->resolveIdentifier($context));
    }

    public function testResolveActorIdentifierForSalesChannelContextIsAnonymous(): void
    {
        $context = new Context(new SalesChannelApiSource(Uuid::randomHex()));

        static::assertSame(StorefrontVisitor::IDENTIFIER, $this->scope->resolveActorIdentifier($context));
    }

    public function testResolveIdentifierThrowsForAdminApiSource(): void
    {
        $context = new Context(new AdminApiSource(Uuid::randomHex()));

        $this->expectExceptionObject(ConsentException::cannotResolveScope(StorefrontVisitor::NAME));

        $this->scope->resolveIdentifier($context);
    }

    public function testResolveIdentifierThrowsForSystemSource(): void
    {
        $context = new Context(new SystemSource());

        $this->expectExceptionObject(ConsentException::cannotResolveScope(StorefrontVisitor::NAME));

        $this->scope->resolveIdentifier($context);
    }
}
