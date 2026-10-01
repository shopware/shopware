<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\OAuth\Scope;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OAuth\Scope\IntegrationVerifiedScope;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(IntegrationVerifiedScope::class)]
class IntegrationVerifiedScopeTest extends TestCase
{
    public function testIdentifier(): void
    {
        $scope = new IntegrationVerifiedScope();

        static::assertSame(IntegrationVerifiedScope::IDENTIFIER, $scope->getIdentifier());
        static::assertSame('integration-verified', $scope->jsonSerialize());
    }
}
