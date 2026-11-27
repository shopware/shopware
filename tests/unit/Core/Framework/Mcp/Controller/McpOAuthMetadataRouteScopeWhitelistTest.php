<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Controller\McpOAuthMetadataController;
use Shopware\Core\Framework\Mcp\Controller\McpOAuthMetadataRouteScopeWhitelist;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpOAuthMetadataRouteScopeWhitelist::class)]
class McpOAuthMetadataRouteScopeWhitelistTest extends TestCase
{
    public function testAppliesOnlyToTheMetadataController(): void
    {
        $whitelist = new McpOAuthMetadataRouteScopeWhitelist();

        static::assertTrue($whitelist->applies(McpOAuthMetadataController::class));
        static::assertFalse($whitelist->applies(self::class));
    }
}
