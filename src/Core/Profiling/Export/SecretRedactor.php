<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Export;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * @internal
 */
#[Package('framework')]
class SecretRedactor
{
    public const MARKER = '[redacted]';

    private const SECRET_WORDS = [
        'apikey',
        'auth',
        'authorization',
        'cookie',
        'cookies',
        'credential',
        'credentials',
        'dsn',
        'passphrase',
        'passwd',
        'password',
        'pw',
        'pwd',
        'secret',
        'secrets',
        'token',
        'tokens',
    ];

    private const SECRET_WORD_PAIRS = [
        'access key',
        'api key',
        'private key',
        'session attributes',
    ];

    private const ALLOWED_KEYS = [
        '_stopwatch_token',
        'x-debug-token',
        'x-debug-token-link',
    ];

    private const MIN_LEARNED_LENGTH = 16;

    private const MAX_LEARN_DEPTH = 8;

    /**
     * @var list<string>
     */
    private array $knownSecrets = [];

    /**
     * @param list<string> $secrets
     */
    public function withKnownSecrets(array $secrets): self
    {
        $redactor = clone $this;
        $redactor->knownSecrets = $secrets;

        return $redactor;
    }

    /**
     * @return list<string>
     */
    public function learnSecrets(mixed $data): array
    {
        $secrets = [];
        $this->collectSecrets($data, false, 0, $secrets);

        return array_values(array_unique($secrets));
    }

    public function isSecretKey(int|string $key): bool
    {
        if (\is_int($key) || $key === '') {
            return false;
        }

        $lowerKey = mb_strtolower($key);
        if (\in_array($lowerKey, self::ALLOWED_KEYS, true)) {
            return false;
        }

        $words = preg_split('/[^a-z0-9]+/', mb_strtolower((string) preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $key)), -1, \PREG_SPLIT_NO_EMPTY) ?: [];

        if (array_intersect($words, self::SECRET_WORDS) !== []) {
            return true;
        }

        if (preg_match('/^[A-Z0-9_]+_(KEY|PASS)$/D', $key) === 1) {
            return true;
        }

        for ($i = 1, $count = \count($words); $i < $count; ++$i) {
            if (\in_array($words[$i - 1] . ' ' . $words[$i], self::SECRET_WORD_PAIRS, true)) {
                return true;
            }
        }

        return false;
    }

    public function redactString(string $value): string
    {
        if ($this->knownSecrets !== []) {
            $value = str_replace($this->knownSecrets, self::MARKER, $value);
        }

        if (preg_match('/^\s*(Bearer|Basic)\s+\S+\s*$/iD', $value, $match) === 1) {
            return $match[1] . ' ' . self::MARKER;
        }

        $value = (string) preg_replace('/\beyJ[A-Za-z0-9_-]{5,}\.eyJ[A-Za-z0-9_-]{5,}\.[A-Za-z0-9_-]*/', self::MARKER, $value);
        $value = (string) preg_replace_callback(
            '#\b([a-z][a-z0-9+.\-]*://)(?:([^\s/:@?\#]*):)?[^\s/@?\#]+@#i',
            static fn (array $match): string => $match[1] . (($match[2] ?? '') !== '' ? $match[2] . ':' : '') . self::MARKER . '@',
            $value,
        );

        $value = (string) preg_replace_callback(
            '/(^|[\s"\'?&;,(])(-{0,2})([\w.\[\]-]+)=("[^"]*"|\'[^\']*\'|[^\s&;,"\')]*)/',
            fn (array $match): string => $this->isSecretKey(rawurldecode($match[3])) ? $match[1] . $match[2] . $match[3] . '=' . self::MARKER : $match[0],
            $value,
        );

        $value = (string) preg_replace_callback(
            '/(^|[\r\n\'"]|[<>] )([A-Za-z][\w-]*):[ \t]*([^\r\n\'"]+)/',
            fn (array $match): string => $this->isSecretKey($match[2]) ? $match[1] . $match[2] . ': ' . self::MARKER : $match[0],
            $value,
        );

        return (string) preg_replace_callback(
            '/(^|\s)(--?[\w.-]+)\s+(\'[^\']*\'|"[^"]*"|[^\s\'"-]\S*)/',
            fn (array $match): string => $this->isSecretKey($match[2]) ? $match[1] . $match[2] . ' ' . self::MARKER : $match[0],
            $value,
        );
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public function decodeBody(string $body): ?array
    {
        $body = trim($body);
        if ($body === '') {
            return null;
        }

        if ($body[0] === '{' || $body[0] === '[') {
            $decoded = json_decode($body, true);

            return \is_array($decoded) ? $decoded : null;
        }

        if (preg_match('/^[^=&\s]+=[^&\s]*(&[^=&\s]+=[^&\s]*)*$/D', $body) !== 1) {
            return null;
        }

        parse_str($body, $fields);

        return $fields;
    }

    /**
     * @param list<string> $secrets
     */
    private function collectSecrets(mixed $value, bool $secret, int $depth, array &$secrets): void
    {
        if ($value instanceof Data) {
            $value = $value->getValue();
        }

        if (\is_array($value)) {
            if ($depth < self::MAX_LEARN_DEPTH) {
                foreach ($value as $key => $child) {
                    $this->collectSecrets($child, \is_int($key) ? $secret : $this->isSecretKey($key), $depth + 1, $secrets);
                }
            }

            return;
        }

        if (!$secret || !\is_string($value)) {
            return;
        }

        if (preg_match('/^\s*(?:Bearer|Basic)\s+(\S+)\s*$/iD', $value, $match) === 1) {
            $value = $match[1];
        }

        if (mb_strlen($value) >= self::MIN_LEARNED_LENGTH) {
            $secrets[] = $value;
        }
    }
}
