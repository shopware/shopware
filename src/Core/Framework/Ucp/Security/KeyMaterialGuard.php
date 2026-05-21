<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Security;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Monolog processor that strips any context key matching known private-key
 * patterns before serialisation. Provides defence-in-depth against accidental
 * key-material leakage through logs.
 *
 * @internal
 */
#[Package('framework')]
class KeyMaterialGuard implements ProcessorInterface
{
    private const SENSITIVE_PATTERNS = [
        '/private[_-]?key/i',
        '/private[_-]?jwk/i',
        '/pem[_-]?encrypted/i',
        '/signing[_-]?secret/i',
        '/jwk\.d$/i',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $this->scrub($record->context);
        $extra = $this->scrub($record->extra);

        return $record->with(context: $context, extra: $extra);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function scrub(array $data): array
    {
        foreach ($data as $key => $value) {
            $stringKey = \is_string($key) ? $key : (string) $key;

            foreach (self::SENSITIVE_PATTERNS as $pattern) {
                if (preg_match($pattern, $stringKey) === 1) {
                    $data[$key] = '[redacted]';
                    continue 2;
                }
            }

            if (\is_array($value)) {
                $data[$key] = $this->scrub($value);
            }
        }

        return $data;
    }
}
