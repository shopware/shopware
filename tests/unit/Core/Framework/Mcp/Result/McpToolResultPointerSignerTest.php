<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Result;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Result\McpToolResultPointerSigner;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpToolResultPointerSigner::class)]
class McpToolResultPointerSignerTest extends TestCase
{
    public function testASignedPointerVerifiesForThePrincipalItWasIssuedTo(): void
    {
        $clock = new MockClock('2026-09-28T10:00:00+00:00');
        $signer = new McpToolResultPointerSigner('secret', $clock);
        $id = Uuid::randomHex();

        $pointer = $signer->sign($id, 'admin:integration-a:');

        static::assertSame($id, $signer->verify($pointer->token, 'admin:integration-a:'));
        static::assertSame('shopware://tool-result/' . $pointer->token, $pointer->uri());
        static::assertSame('2026-09-28T11:00:00+00:00', $pointer->expiresAt->format(\DateTimeInterface::ATOM));
    }

    public function testAnotherPrincipalCannotUseThePointer(): void
    {
        $signer = new McpToolResultPointerSigner('secret', new MockClock());

        $pointer = $signer->sign(Uuid::randomHex(), 'store:sales-channel:token-a');

        static::assertNull($signer->verify($pointer->token, 'store:sales-channel:token-b'));
    }

    public function testAnotherSecretDoesNotAcceptThePointer(): void
    {
        $clock = new MockClock();

        $pointer = (new McpToolResultPointerSigner('secret', $clock))->sign(Uuid::randomHex(), 'p');

        static::assertNull((new McpToolResultPointerSigner('other-secret', $clock))->verify($pointer->token, 'p'));
    }

    public function testThePointerExpires(): void
    {
        $clock = new MockClock('2026-09-28T10:00:00+00:00');
        $signer = new McpToolResultPointerSigner('secret', $clock);
        $pointer = $signer->sign(Uuid::randomHex(), 'p');

        $clock->sleep(McpToolResultPointerSigner::TTL_SECONDS);
        static::assertNotNull($signer->verify($pointer->token, 'p'), 'still valid at the expiry second');

        $clock->sleep(1);
        static::assertNull($signer->verify($pointer->token, 'p'));
    }

    public function testAChangedExpiryInvalidatesTheSignature(): void
    {
        $signer = new McpToolResultPointerSigner('secret', new MockClock());
        [$id, $expiry, $signature] = explode('.', $signer->sign(Uuid::randomHex(), 'p')->token);

        static::assertNull($signer->verify($id . '.' . ((int) $expiry + 86400) . '.' . $signature, 'p'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedTokenProvider(): iterable
    {
        yield 'a plain id' => [Uuid::randomHex()];
        yield 'too many parts' => ['a.b.c.d'];
        yield 'an id that is not hex' => [str_repeat('z', 32) . '.1.sig'];
        yield 'an id of the wrong length' => ['abcdef.1.sig'];
        yield 'an expiry that is not a number' => [Uuid::randomHex() . '.soon.sig'];
    }

    #[DataProvider('malformedTokenProvider')]
    public function testAMalformedTokenIsRejected(string $token): void
    {
        static::assertNull((new McpToolResultPointerSigner('secret', new MockClock()))->verify($token, 'p'));
    }
}
