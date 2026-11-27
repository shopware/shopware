<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\Export;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * @internal
 */
#[Package('framework')]
class CollectorDataConverter
{
    public function convert(mixed $value, ExportLimits $limits, ?SecretRedactor $redactor = null): mixed
    {
        return $this->walk($value, $limits, $redactor, 0, false, []);
    }

    /**
     * @param array<int, true> $objectPath
     */
    private function walk(mixed $value, ExportLimits $limits, ?SecretRedactor $redactor, int $depth, bool $secret, array $objectPath): mixed
    {
        if ($value instanceof Data) {
            $children = $value->getValue();
            if (!\is_array($children)) {
                return $this->scalar($children, $limits, $redactor, $secret);
            }

            $type = $value->getType();
            $class = $type === null || $type === 'array' ? null : $type;

            return $this->walkChildren($children, $class, $limits, $redactor, $depth, $secret, $objectPath);
        }

        if (\is_array($value)) {
            return $this->walkChildren($value, null, $limits, $redactor, $depth, $secret, $objectPath);
        }

        if ($value instanceof \DateTimeInterface) {
            return $this->scalar($value->format(\DATE_ATOM), $limits, $redactor, $secret);
        }

        if ($value instanceof \UnitEnum) {
            return $this->scalar($value instanceof \BackedEnum ? $value->value : $value->name, $limits, $redactor, $secret);
        }

        if (\is_object($value)) {
            $id = spl_object_id($value);
            if (isset($objectPath[$id])) {
                return '[recursion ' . $value::class . ']';
            }

            return $this->walkChildren((array) $value, $value::class, $limits, $redactor, $depth, $secret, $objectPath + [$id => true]);
        }

        return $this->scalar($value, $limits, $redactor, $secret);
    }

    /**
     * @param array<array-key, mixed> $children
     * @param array<int, true> $objectPath
     *
     * @return array<array-key, mixed>|string
     */
    private function walkChildren(array $children, ?string $class, ExportLimits $limits, ?SecretRedactor $redactor, int $depth, bool $secret, array $objectPath): array|string
    {
        $count = \count($children);
        if ($limits->depth > 0 && $depth >= $limits->depth) {
            return \sprintf('[%s(%d) beyond depth]', $class ?? 'array', $count);
        }

        $result = $class === null ? [] : ['@class' => $class];
        $isList = $class === null && array_is_list($children);
        $kept = $limits->items > 0 ? \array_slice($children, 0, $limits->items, true) : $children;

        foreach ($kept as $key => $child) {
            $key = $this->propertyName($key);
            $childSecret = $secret || ($redactor !== null && $redactor->isSecretKey($key));

            if ($redactor !== null && !$childSecret && $key === 'content') {
                $child = $this->decodeBody($child, $redactor);
            }

            $result[$key] = $this->walk($child, $limits, $redactor, $depth + 1, $childSecret, $objectPath);
        }

        $remaining = $count - \count($kept);
        if ($remaining > 0) {
            $marker = \sprintf('[… %d more]', $remaining);
            if ($isList) {
                $result[] = $marker;
            } else {
                $result['…'] = $marker;
            }
        }

        return $result;
    }

    private function scalar(mixed $value, ExportLimits $limits, ?SecretRedactor $redactor, bool $secret): mixed
    {
        if ($redactor !== null && $secret && $value !== null && $value !== '' && !\is_bool($value)) {
            return SecretRedactor::MARKER;
        }

        if (\is_resource($value) || \gettype($value) === 'resource (closed)') {
            return 'resource';
        }

        if (\is_float($value) && !is_finite($value)) {
            return (string) $value;
        }

        if (!\is_string($value)) {
            return $value;
        }

        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = bin2hex($value);
        }

        if ($redactor !== null) {
            $value = $redactor->redactString($value);
        }

        if ($limits->string <= 0) {
            return $value;
        }

        $length = mb_strlen($value);
        if ($length <= $limits->string) {
            return $value;
        }

        return mb_substr($value, 0, $limits->string) . \sprintf('…[%d more chars]', $length - $limits->string);
    }

    private function decodeBody(mixed $body, SecretRedactor $redactor): mixed
    {
        $string = $body instanceof Data ? $body->getValue() : $body;
        if (!\is_string($string)) {
            return $body;
        }

        return $redactor->decodeBody($string) ?? $body;
    }

    private function propertyName(int|string $key): int|string
    {
        if (\is_string($key) && str_starts_with($key, "\0")) {
            return substr($key, (int) strrpos($key, "\0") + 1);
        }

        return $key;
    }
}
