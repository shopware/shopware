<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Upload;

use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Mints and verifies the opaque upload token that carries the presigned-upload context between the request and confirm
 * steps without persisting anything. The payload is base64url-encoded and authenticated with an HMAC keyed by the
 * application secret, so a client cannot forge or alter it (e.g. flip visibility or point confirm at another key).
 */
#[Package('discovery')]
readonly class PresignedUploadTokenSigner
{
    private const SEPARATOR = '.';

    public function __construct(private string $secret)
    {
    }

    public function sign(PresignedUploadToken $token): string
    {
        $payload = self::encode([
            'mediaId' => $token->mediaId,
            'path' => $token->path,
            'fileName' => $token->fileName,
            'extension' => $token->extension,
            'mimeType' => $token->mimeType,
            'private' => $token->private,
            'mediaFolderId' => $token->mediaFolderId,
            'deduplicate' => $token->deduplicate,
            'isReplace' => $token->isReplace,
            'uploadedAt' => $token->uploadedAt->format(\DateTimeInterface::ATOM),
            'expiresAt' => $token->expiresAt->format(\DateTimeInterface::ATOM),
        ]);

        return $payload . self::SEPARATOR . $this->signature($payload);
    }

    public function verify(string $token, \DateTimeImmutable $now): PresignedUploadToken
    {
        $parts = explode(self::SEPARATOR, $token);
        if (\count($parts) !== 2) {
            throw MediaException::presignedUploadTokenInvalid();
        }

        [$payload, $signature] = $parts;

        // Constant-time compare so a mismatch does not leak where the signature diverges.
        if (!hash_equals($this->signature($payload), $signature)) {
            throw MediaException::presignedUploadTokenInvalid();
        }

        $data = self::decode($payload);

        $expiresAt = self::readDate($data, 'expiresAt');
        if ($now > $expiresAt) {
            throw MediaException::presignedUploadTokenExpired();
        }

        return new PresignedUploadToken(
            mediaId: self::readString($data, 'mediaId'),
            path: self::readString($data, 'path'),
            fileName: self::readString($data, 'fileName'),
            extension: self::readString($data, 'extension'),
            mimeType: self::readString($data, 'mimeType'),
            private: self::readBool($data, 'private'),
            mediaFolderId: self::readNullableString($data, 'mediaFolderId'),
            deduplicate: self::readBool($data, 'deduplicate'),
            isReplace: self::readBool($data, 'isReplace'),
            uploadedAt: self::readDate($data, 'uploadedAt'),
            expiresAt: $expiresAt,
        );
    }

    private function signature(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->secret);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function encode(array $data): string
    {
        return rtrim(strtr(base64_encode(json_encode($data, \JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(string $payload): array
    {
        $json = base64_decode(strtr($payload, '-_', '+/'), true);
        if ($json === false) {
            throw MediaException::presignedUploadTokenInvalid();
        }

        try {
            $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw MediaException::presignedUploadTokenInvalid();
        }

        if (!\is_array($data)) {
            throw MediaException::presignedUploadTokenInvalid();
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function readString(array $data, string $key): string
    {
        if (!isset($data[$key]) || !\is_string($data[$key])) {
            throw MediaException::presignedUploadTokenInvalid();
        }

        return $data[$key];
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function readNullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if ($value !== null && !\is_string($value)) {
            throw MediaException::presignedUploadTokenInvalid();
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function readBool(array $data, string $key): bool
    {
        if (!isset($data[$key]) || !\is_bool($data[$key])) {
            throw MediaException::presignedUploadTokenInvalid();
        }

        return $data[$key];
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function readDate(array $data, string $key): \DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable(self::readString($data, $key));
        } catch (\Exception) {
            throw MediaException::presignedUploadTokenInvalid();
        }
    }
}
