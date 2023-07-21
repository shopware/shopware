<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldType;

use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class DateInterval extends \DateInterval
{
    public const FORMAT = 'P%yY%mM%dDT%hH%iM%sS';

    public function __toString(): string
    {
        return $this->format(self::FORMAT);
    }

    public function equals(DateInterval $other): bool
    {
        return $this->y === $other->y
            && $this->m === $other->m
            && $this->d === $other->d
            && $this->h === $other->h
            && $this->i === $other->i
            && $this->s === $other->s
            && $this->invert === $other->invert;
    }

    public function isEmpty(): bool
    {
        return $this->y === 0
            && $this->m === 0
            && $this->d === 0
            && $this->h === 0
            && $this->i === 0
            && $this->s === 0;
    }

    public static function createFromDateString(string $datetime): \DateInterval|false
    {
        return static::createFromString($datetime) ?? false;
    }

    public static function createFromString(string $datetime): ?DateInterval
    {
        try {
            return new self($datetime);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function createFromDateInterval(\DateInterval $datetime): self
    {
        return new self($datetime->format(self::FORMAT));
    }
}
