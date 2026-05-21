<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Transport\Rest;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Ucp\Transport\Rest\UcpAgentHeader;
use Shopware\Core\Framework\Ucp\UcpException;

/**
 * @internal
 */
#[CoversClass(UcpAgentHeader::class)]
class UcpAgentHeaderTest extends TestCase
{
    public function testParsesProfile(): void
    {
        $header = UcpAgentHeader::parse('profile="https://agent.example/profile.json"');
        static::assertSame('https://agent.example/profile.json', $header->profileUri);
    }

    public function testParsesAdditionalParameters(): void
    {
        $header = UcpAgentHeader::parse('profile="https://x/p", tag="ucp"');
        static::assertSame('https://x/p', $header->profileUri);
        static::assertSame('ucp', $header->additionalParameters['tag'] ?? null);
    }

    public function testEmptyValueRejected(): void
    {
        $this->expectException(UcpException::class);
        UcpAgentHeader::parse('');
    }

    public function testMissingProfileRejected(): void
    {
        $this->expectException(UcpException::class);
        UcpAgentHeader::parse('foo="bar"');
    }
}
