<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ContextTokenStruct;
use Shopware\Core\PlatformRequest;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContextTokenStruct::class)]
class ContextTokenStructTest extends TestCase
{
    public function testJsonSerializeExposesTheTokenAsContextTokenHeader(): void
    {
        $struct = new ContextTokenStruct('the-token');

        $data = $struct->jsonSerialize();

        static::assertArrayNotHasKey('token', $data);
        static::assertSame('the-token', $data[PlatformRequest::HEADER_CONTEXT_TOKEN]);
    }

    public function testApiAlias(): void
    {
        static::assertSame('context_token', (new ContextTokenStruct('the-token'))->getApiAlias());
    }
}
