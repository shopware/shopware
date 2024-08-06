<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(AccessKeyHelper::class)]
class AccessKeyHelperTest extends TestCase
{
    #[DataProvider('mappingIdentifier')]
    public function testGenerateAccessKeyWithUserIdentifier(string $origin, string $identifier): void
    {
        $accessKey = AccessKeyHelper::generateAccessKey($identifier);
        static::assertStringContainsString($origin, $accessKey);
    }

    public function testGenerateAccessKeyWithInvalidIdentifier(): void
    {
        static::expectException(ApiException::class);
        static::expectExceptionMessage('Given identifier for access key is invalid.');
        AccessKeyHelper::generateAccessKey('invalid_identifier');
    }

    public function testGenerateOriginWithIntegrationIdentifier(): void
    {
        $accessKey = AccessKeyHelper::generateAccessKey('integration');
        $origin = AccessKeyHelper::getOrigin($accessKey);
        static::assertSame('integration', $origin);
    }

    public function testGenerateOriginWithInvalidAccessKey(): void
    {
        static::expectExceptionMessage('Access key is invalid and could not be identified.');
        static::expectException(ApiException::class);
        AccessKeyHelper::getOrigin('invalid_access_key');
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function mappingIdentifier(): array
    {
        return [
            ['SWUA', 'user'],
            ['SWIA', 'integration'],
            ['SWSC', 'sales-channel'],
            ['SWPE', 'product-export'],
        ];
    }
}
