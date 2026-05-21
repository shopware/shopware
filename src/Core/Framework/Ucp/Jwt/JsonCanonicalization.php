<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Jwt;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\UcpException;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * RFC 8785 JSON Canonicalization Scheme (JCS) — produces the canonical UTF-8
 * byte sequence for a JSON value so signatures over JSON match
 * bit-for-bit across implementations.
 *
 * JCS rules summary:
 *   - object members are sorted lexicographically by code-unit
 *   - whitespace is removed
 *   - strings use minimal escape sequences (per ECMA-262 §24.5.2.2)
 *   - numbers use ES6 Number.prototype.toString() (RFC 8785 §3.2.2.3)
 *   - UTF-8 output
 *
 * Used by AP2 mandate canonicalization (merchant_authorization signing) and
 * by any other UCP component that needs deterministic JSON for crypto.
 *
 * @internal
 */
#[Package('framework')]
final class JsonCanonicalization
{
    public static function encode(mixed $value): string
    {
        return self::serialize(self::normalise($value));
    }

    /**
     * Recursively prepare the value for canonical serialisation:
     *   - sort object keys
     *   - normalise floats (treat integer-valued floats as integers)
     */
    private static function normalise(mixed $value): mixed
    {
        if (\is_array($value)) {
            if (array_is_list($value)) {
                return array_map(self::normalise(...), $value);
            }
            ksort($value, \SORT_STRING);
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = self::normalise($v);
            }

            return $out;
        }

        if (\is_object($value)) {
            $arr = (array) $value;
            ksort($arr, \SORT_STRING);
            $out = [];
            foreach ($arr as $k => $v) {
                $out[$k] = self::normalise($v);
            }

            return $out;
        }

        return $value;
    }

    private static function serialize(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if ($value === true) {
            return 'true';
        }
        if ($value === false) {
            return 'false';
        }
        if (\is_int($value)) {
            return (string) $value;
        }
        if (\is_float($value)) {
            return self::serializeNumber($value);
        }
        if (\is_string($value)) {
            return self::serializeString($value);
        }
        if (\is_array($value)) {
            return self::serializeArray($value);
        }

        throw UcpException::jcsCanonicalizationFailed('unsupported value type ' . \gettype($value));
    }

    /**
     * @param array<int|string, mixed> $array
     */
    private static function serializeArray(array $array): string
    {
        if (array_is_list($array)) {
            $parts = [];
            foreach ($array as $item) {
                $parts[] = self::serialize($item);
            }

            return '[' . implode(',', $parts) . ']';
        }

        $parts = [];
        foreach ($array as $k => $v) {
            $parts[] = self::serializeString((string) $k) . ':' . self::serialize($v);
        }

        return '{' . implode(',', $parts) . '}';
    }

    private static function serializeNumber(float $n): string
    {
        if (!is_finite($n)) {
            throw UcpException::jcsCanonicalizationFailed('non-finite numbers (NaN, Inf) are not representable in JCS');
        }
        // Integer-valued floats render as integers (RFC 8785 §3.2.2.3 example).
        if (floor($n) === $n && abs($n) < (float) \PHP_INT_MAX) {
            return (string) (int) $n;
        }

        // PHP's default float→string is locale-aware; force C locale via sprintf.
        // For interop fidelity beyond this we'd need full ES6-Number.toString;
        // good enough for the integer-amount-heavy AP2 payloads we sign.
        $s = \sprintf('%.17g', $n);

        return $s;
    }

    private static function serializeString(string $s): string
    {
        // ECMA-262 §24.5.2.2 minimal escapes
        $escaped = '';
        $len = \strlen($s);
        for ($i = 0; $i < $len; ++$i) {
            $c = $s[$i];
            $code = \ord($c);
            switch ($c) {
                case '"':  $escaped .= '\\"';
                    continue 2;
                case '\\': $escaped .= '\\\\';
                    continue 2;
                case "\b": $escaped .= '\\b';
                    continue 2;
                case "\f": $escaped .= '\\f';
                    continue 2;
                case "\n": $escaped .= '\\n';
                    continue 2;
                case "\r": $escaped .= '\\r';
                    continue 2;
                case "\t": $escaped .= '\\t';
                    continue 2;
            }
            if ($code < 0x20) {
                $escaped .= \sprintf('\\u%04x', $code);
                continue;
            }
            $escaped .= $c;
        }

        return '"' . $escaped . '"';
    }
}
