<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Order;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class TestError extends Error
{
    public const LEVEL_UNKNOWN = \PHP_INT_MAX;

    private int $level;

    private function __construct(int $level, bool $blockOrder = true, bool $blockResubmit = true)
    {
        $this->level = $level;
        $this->blockOrderVal = $blockOrder;
        $this->blockResubmitVal = $blockResubmit;
    }

    public static function error(bool $blockOrder = true, bool $blockResubmit = true): self
    {
        return new self(self::LEVEL_ERROR, $blockOrder, $blockResubmit);
    }

    public static function warn(bool $blockOrder = true, bool $blockResubmit = true): self
    {
        return new self(self::LEVEL_WARNING, $blockOrder, $blockResubmit);
    }

    public static function notice(bool $blockOrder = true, bool $blockResubmit = true): self
    {
        return new self(self::LEVEL_NOTICE, $blockOrder, $blockResubmit);
    }

    public static function unknown(bool $blockOrder = true, bool $blockResubmit = true): self
    {
        return new self(self::LEVEL_UNKNOWN, $blockOrder, $blockResubmit);
    }

    public function getId(): string
    {
        return \sha1('foo_' . $this->level . Uuid::randomHex());
    }

    public function getMessageKey(): string
    {
        return 'LoremIpsumDolorSit';
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function blockOrder(): bool
    {
        return $this->blockOrderVal;
    }

    public function blockResubmit(): bool
    {
        return $this->blockResubmitVal;
    }

    public function getParameters(): array
    {
        return [];
    }
}
