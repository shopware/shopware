<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Upload;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\Upload\PresignedUploadToken;
use Shopware\Core\Content\Media\Upload\PresignedUploadTokenSigner;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(PresignedUploadTokenSigner::class)]
class PresignedUploadTokenSignerTest extends TestCase
{
    private PresignedUploadTokenSigner $signer;

    protected function setUp(): void
    {
        $this->signer = new PresignedUploadTokenSigner('s3cr3t');
    }

    public function testSignAndVerifyRoundTrip(): void
    {
        $token = $this->createToken();

        $decoded = $this->signer->verify($this->signer->sign($token), new \DateTimeImmutable('2026-02-10T11:00:00+00:00'));

        static::assertSame($token->mediaId, $decoded->mediaId);
        static::assertSame($token->path, $decoded->path);
        static::assertSame($token->fileName, $decoded->fileName);
        static::assertSame($token->extension, $decoded->extension);
        static::assertSame($token->mimeType, $decoded->mimeType);
        static::assertSame($token->private, $decoded->private);
        static::assertSame($token->mediaFolderId, $decoded->mediaFolderId);
        static::assertSame($token->deduplicate, $decoded->deduplicate);
        static::assertSame($token->isReplace, $decoded->isReplace);
        static::assertEquals($token->uploadedAt, $decoded->uploadedAt);
        static::assertEquals($token->expiresAt, $decoded->expiresAt);
    }

    public function testVerifyRejectsTamperedPayload(): void
    {
        [$payload, $signature] = explode('.', $this->signer->sign($this->createToken()));

        // Mutate the payload without re-signing; the HMAC must no longer match.
        $tampered = ($payload[0] === 'A' ? 'B' : 'A') . substr($payload, 1);

        $this->expectException(MediaException::class);
        $this->expectExceptionMessageMatches('/token is invalid/');
        $this->signer->verify($tampered . '.' . $signature, new \DateTimeImmutable('2026-02-10T11:00:00+00:00'));
    }

    public function testVerifyRejectsUnknownSignature(): void
    {
        [$payload] = explode('.', $this->signer->sign($this->createToken()));

        $this->expectException(MediaException::class);
        $this->signer->verify($payload . '.deadbeef', new \DateTimeImmutable('2026-02-10T11:00:00+00:00'));
    }

    public function testVerifyRejectsMalformedToken(): void
    {
        $this->expectException(MediaException::class);
        $this->signer->verify('not-a-valid-token', new \DateTimeImmutable('2026-02-10T11:00:00+00:00'));
    }

    public function testVerifyRejectsExpiredToken(): void
    {
        $signed = $this->signer->sign($this->createToken());

        $this->expectException(MediaException::class);
        $this->expectExceptionMessageMatches('/expired/');
        $this->signer->verify($signed, new \DateTimeImmutable('2026-02-10T13:00:00+00:00'));
    }

    private function createToken(): PresignedUploadToken
    {
        return new PresignedUploadToken(
            mediaId: '0191c0d1e0e17c8fb0b4a2c3d4e5f601',
            path: 'media/ab/cd/test-file.jpg',
            fileName: 'test-file',
            extension: 'jpg',
            mimeType: 'image/jpeg',
            private: false,
            mediaFolderId: null,
            deduplicate: false,
            isReplace: false,
            uploadedAt: new \DateTimeImmutable('2026-02-10T10:00:00+00:00'),
            expiresAt: new \DateTimeImmutable('2026-02-10T12:00:00+00:00'),
        );
    }
}
