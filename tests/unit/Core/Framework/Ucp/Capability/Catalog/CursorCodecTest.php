<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Capability\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Ucp\Capability\Catalog\CursorCodec;
use Shopware\Core\Framework\Ucp\UcpException;

/**
 * @internal
 */
#[CoversClass(CursorCodec::class)]
class CursorCodecTest extends TestCase
{
    private CursorCodec $codec;

    protected function setUp(): void
    {
        // CursorCodec derives its HMAC key from APP_SECRET via
        // `EnvironmentHelper::getVariable()`. Stub a deterministic value so
        // the test does not depend on the host environment.
        $_SERVER['APP_SECRET'] = 'unit-test-fixed-secret';
        $_ENV['APP_SECRET'] = 'unit-test-fixed-secret';

        $this->codec = new CursorCodec();
    }

    public function testRoundTripPageMode(): void
    {
        $fp = $this->codec->fingerprint('shoes', ['filters' => ['price' => ['min' => 1000]]]);

        $cursor = $this->codec->encode([
            'mode' => CursorCodec::MODE_PAGE,
            'page' => 3,
            'q' => $fp,
        ]);

        $decoded = $this->codec->decode($cursor, $fp);

        static::assertSame(CursorCodec::MODE_PAGE, $decoded['mode']);
        static::assertSame(3, $decoded['page']);
        static::assertNull($decoded['after']);
        static::assertSame($fp, $decoded['q']);
    }

    public function testRoundTripAfterMode(): void
    {
        $fp = $this->codec->fingerprint('', []);

        $cursor = $this->codec->encode([
            'mode' => CursorCodec::MODE_AFTER,
            'after' => '0123456789abcdef0123456789abcdef',
            'q' => $fp,
        ]);

        $decoded = $this->codec->decode($cursor, $fp);

        static::assertSame(CursorCodec::MODE_AFTER, $decoded['mode']);
        static::assertSame('0123456789abcdef0123456789abcdef', $decoded['after']);
        static::assertNull($decoded['page']);
    }

    public function testTamperedSignatureIsRejected(): void
    {
        $fp = $this->codec->fingerprint('shoes', []);
        $cursor = $this->codec->encode(['mode' => CursorCodec::MODE_PAGE, 'page' => 2, 'q' => $fp]);

        [$payload, $signature] = explode('.', $cursor);
        // Mutate the first signature character so the base64url padding bits at
        // the end cannot accidentally decode to the same raw HMAC bytes.
        $tamperedSignature = (str_starts_with($signature, 'A') ? 'B' : 'A') . substr($signature, 1);
        $tampered = $payload . '.' . $tamperedSignature;

        $this->expectException(UcpException::class);
        $this->expectExceptionMessageMatches('/cursor signature mismatch/');
        $this->codec->decode($tampered, $fp);
    }

    public function testTamperedPayloadIsRejected(): void
    {
        $fp = $this->codec->fingerprint('shoes', []);
        $cursor = $this->codec->encode(['mode' => CursorCodec::MODE_PAGE, 'page' => 2, 'q' => $fp]);

        [$payload, $sig] = explode('.', $cursor);
        // Re-encode a different payload while keeping the original signature.
        $forgedPayload = rtrim(strtr(base64_encode('{"v":1,"mode":"page","page":99,"q":"' . $fp . '","iat":' . time() . '}'), '+/', '-_'), '=');
        $forged = $forgedPayload . '.' . $sig;

        $this->expectException(UcpException::class);
        $this->codec->decode($forged, $fp);
    }

    public function testCursorBoundToOriginatingQuery(): void
    {
        $fpShoes = $this->codec->fingerprint('shoes', []);
        $fpJackets = $this->codec->fingerprint('jackets', []);
        $cursor = $this->codec->encode(['mode' => CursorCodec::MODE_PAGE, 'page' => 2, 'q' => $fpShoes]);

        $this->expectException(UcpException::class);
        $this->expectExceptionMessageMatches('/query fingerprint mismatch/');
        $this->codec->decode($cursor, $fpJackets);
    }

    public function testFingerprintIsOrderInsensitive(): void
    {
        $a = $this->codec->fingerprint('q', ['price' => ['min' => 1, 'max' => 2], 'category_id' => 'c']);
        $b = $this->codec->fingerprint('q', ['category_id' => 'c', 'price' => ['max' => 2, 'min' => 1]]);

        static::assertSame($a, $b, 'Fingerprint must not depend on field order');
    }

    public function testDifferentFiltersProduceDifferentFingerprint(): void
    {
        $a = $this->codec->fingerprint('q', ['category_id' => 'a']);
        $b = $this->codec->fingerprint('q', ['category_id' => 'b']);

        static::assertNotSame($a, $b);
    }

    public function testMalformedCursorRejected(): void
    {
        $this->expectException(UcpException::class);
        $this->codec->decode('this-is-not-a-cursor', 'whatever');
    }

    public function testCursorWithoutSignatureSegmentRejected(): void
    {
        $this->expectException(UcpException::class);
        $this->codec->decode('only-one-segment', 'whatever');
    }
}
