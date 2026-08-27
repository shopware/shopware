<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Service;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
final class CustomerNumberRangePatternMatcher
{
    private const DEFAULT_DATE_FORMAT = 'Y-m-d';

    public function extractIncrement(string $pattern, string $customerNumber): ?int
    {
        if (!\str_contains($pattern, '{n}')) {
            return null;
        }

        $tokens = preg_split('/(\{[^{}]+\})/', $pattern, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY);
        if ($tokens === false) {
            return null;
        }

        $memo = [];

        return $this->parsePatternTokens($tokens, $customerNumber, 0, 0, $memo);
    }

    /**
     * @param list<string> $tokens
     * @param array<string, int|null> $memoryArray
     */
    private function parsePatternTokens(
        array $tokens,
        string $customerNumber,
        int $tokenIndex,
        int $valueOffset,
        array &$memoryArray,
    ): ?int {
        $memoKey = $tokenIndex . ':' . $valueOffset;
        if (\array_key_exists($memoKey, $memoryArray)) {
            return $memoryArray[$memoKey];
        }

        if ($tokenIndex === \count($tokens)) {
            return $memoryArray[$memoKey] = $valueOffset === \strlen($customerNumber) ? 0 : null;
        }

        $token = $tokens[$tokenIndex];
        if (!$this->isPlaceholder($token)) {
            $tokenLength = \strlen($token);
            if (\substr($customerNumber, $valueOffset, $tokenLength) !== $token) {
                return $memoryArray[$memoKey] = null;
            }

            return $memoryArray[$memoKey] = $this->parsePatternTokens(
                $tokens,
                $customerNumber,
                $tokenIndex + 1,
                $valueOffset + $tokenLength,
                $memoryArray,
            );
        }

        $placeholder = \substr($token, 1, -1);
        if ($placeholder === 'n') {
            return $memoryArray[$memoKey] = $this->parseIncrementToken(
                $tokens,
                $customerNumber,
                $tokenIndex,
                $valueOffset,
                $memoryArray,
            );
        }

        if ($placeholder === 'date' || \str_starts_with($placeholder, 'date_')) {
            return $memoryArray[$memoKey] = $this->parseDateToken(
                $tokens,
                $customerNumber,
                $tokenIndex,
                $valueOffset,
                $placeholder === 'date' ? self::DEFAULT_DATE_FORMAT : \substr($placeholder, 5),
                $memoryArray,
            );
        }

        return $memoryArray[$memoKey] = null;
    }

    /**
     * @param list<string> $tokens
     * @param array<string, int|null> $memoryArray
     */
    private function parseIncrementToken(
        array $tokens,
        string $customerNumber,
        int $tokenIndex,
        int $valueOffset,
        array &$memoryArray,
    ): ?int {
        $remaining = \strlen($customerNumber) - $valueOffset;
        $maximumIncrement = null;

        for ($length = 1; $length <= $remaining; ++$length) {
            $value = \substr($customerNumber, $valueOffset, $length);
            if (!\ctype_digit($value)) {
                break;
            }

            $followingIncrement = $this->parsePatternTokens(
                $tokens,
                $customerNumber,
                $tokenIndex + 1,
                $valueOffset + $length,
                $memoryArray,
            );
            if ($followingIncrement === null) {
                continue;
            }

            $increment = (int) $value;
            $maximumIncrement = $maximumIncrement === null ? $increment : \max($maximumIncrement, $increment, $followingIncrement);
        }

        return $maximumIncrement;
    }

    /**
     * @param list<string> $tokens
     * @param array<string, int|null> $memoryArray
     */
    private function parseDateToken(
        array $tokens,
        string $customerNumber,
        int $tokenIndex,
        int $valueOffset,
        string $format,
        array &$memoryArray,
    ): ?int {
        $remaining = \strlen($customerNumber) - $valueOffset;
        $maximumIncrement = null;

        for ($length = 1; $length <= $remaining; ++$length) {
            $dateValue = \substr($customerNumber, $valueOffset, $length);
            if (!$this->isValidDateValue($dateValue, $format)) {
                continue;
            }

            $followingIncrement = $this->parsePatternTokens(
                $tokens,
                $customerNumber,
                $tokenIndex + 1,
                $valueOffset + $length,
                $memoryArray,
            );

            if ($followingIncrement !== null) {
                $maximumIncrement = $maximumIncrement === null ? $followingIncrement : \max($maximumIncrement, $followingIncrement);
            }
        }

        return $maximumIncrement;
    }

    private function isValidDateValue(string $value, string $format): bool
    {
        $date = \DateTimeImmutable::createFromFormat($format, $value);
        $errors = \DateTimeImmutable::getLastErrors();

        return $date !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $date->format($format) === $value;
    }

    private function isPlaceholder(string $value): bool
    {
        return \strlen($value) > 2 && $value[0] === '{' && $value[-1] === '}';
    }
}
