<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\ValueGenerator;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('checkout')]
class NumberRangeGeneratedEvent extends Event
{
    final public const NAME = 'number_range.generated';

    public function __construct(
        private string $generatedValue,
        private readonly string $type,
        private readonly Context $context,
        private readonly ?string $salesChannelId,
        private readonly bool $preview = false
    ) {
    }

    public function getGeneratedValue(): string
    {
        return $this->generatedValue;
    }

    public function setGeneratedValue(string $generatedValue): void
    {
        $this->generatedValue = $generatedValue;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    public function getPreview(): bool
    {
        return $this->preview;
    }
}
