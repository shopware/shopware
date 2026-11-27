<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 *
 * Decodes PHP-serialized payloads such as the `cheapest_price` container of a product.
 *
 * The container is stored once on the parent product but flagged as inherited, so every variant
 * row of a read carries the very same payload. For families with thousands of variants the payload
 * is several megabytes and unserializing it once per row exhausts the memory limit after a few
 * dozen rows. Large payloads are therefore unserialized once per request and the resulting value
 * is shared by all rows that carry the same bytes. The memo is bounded and cleared on kernel reset.
 */
#[Package('framework')]
class PHPUnserializeFieldSerializer extends AbstractFieldSerializer implements ResetInterface
{
    /**
     * Payloads below this size are cheap to unserialize; hashing them would cost more than it saves.
     */
    public const MEMOIZE_MIN_BYTES = 65536;

    /**
     * Upper bound of decoded payloads kept per request. Oldest entries are dropped first.
     */
    public const MEMOIZE_MAX_ENTRIES = 8;

    /**
     * @var array<string, array{value: mixed, bytes: int}>
     */
    private array $memoized = [];

    /**
     * @internal
     */
    public function __construct()
    {
    }

    public function encode(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): \Generator
    {
        throw DataAbstractionLayerException::serializedFieldRequiresIndexer();
    }

    public function decode(Field $field, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (!\is_string($value) || \strlen($value) < self::MEMOIZE_MIN_BYTES) {
            return $this->unserialize($value);
        }

        $bytes = \strlen($value);
        $key = Hasher::hash($value);

        $entry = $this->memoized[$key] ?? null;
        if ($entry !== null && $entry['bytes'] === $bytes) {
            return $entry['value'];
        }

        $decoded = $this->unserialize($value);

        // A hash collision with a different length is treated as a miss and replaced
        unset($this->memoized[$key]);

        while (\count($this->memoized) >= self::MEMOIZE_MAX_ENTRIES) {
            unset($this->memoized[array_key_first($this->memoized)]);
        }

        $this->memoized[$key] = ['value' => $decoded, 'bytes' => $bytes];

        return $decoded;
    }

    public function reset(): void
    {
        $this->memoized = [];
    }

    private function unserialize(mixed $value): mixed
    {
        /** @phpstan-ignore shopware.unserializeUsage */
        return \unserialize($value);
    }
}
