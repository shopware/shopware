<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class CartCompressor
{
    public const COMPRESSION_TYPE_NONE = 0;
    public const COMPRESSION_TYPE_GZIP = 1;
    public const COMPRESSION_TYPE_ZSTD = 2;

    /**
     * @var self::COMPRESSION_TYPE_*
     */
    private int $compressMethod;

    /**
     * @internal
     */
    public function __construct(private bool $compress, string $compressMethod)
    {
        $this->compressMethod = match ($compressMethod) {
            'zstd' => self::COMPRESSION_TYPE_ZSTD,
            'gzip' => self::COMPRESSION_TYPE_GZIP,
            default => throw CartException::invalidCompressionMethod($compressMethod),
        };

        if (!$this->compress) {
            $this->compressMethod = self::COMPRESSION_TYPE_NONE;
        }
    }

    /**
     * @return array{0: self::COMPRESSION_TYPE_*, 1: string}
     */
    public function serialize(mixed $value): array
    {
        $compressed = serialize($value);

        if (!$this->compress) {
            return [$this->compressMethod, $compressed];
        }

        if ($this->compressMethod === self::COMPRESSION_TYPE_ZSTD) {
            $compressed = \zstd_compress($compressed);
        } elseif ($this->compressMethod === self::COMPRESSION_TYPE_GZIP) {
            $compressed = \gzcompress($compressed, 9);
        }

        if ($compressed === false) {
            throw CartException::deserializeFailed();
        }

        return [$this->compressMethod, $compressed];
    }

    public function unserialize(string $value, int $compressionMethod): mixed
    {
        $uncompressed = $value;

        if ($compressionMethod === self::COMPRESSION_TYPE_GZIP) {
            $uncompressed = @\gzuncompress($uncompressed);
        } elseif ($compressionMethod === self::COMPRESSION_TYPE_ZSTD) {
            $uncompressed = @\zstd_uncompress($uncompressed);
        }

        if ($uncompressed === false) {
            throw CartException::deserializeFailed();
        }

        return unserialize($uncompressed);
    }
}
