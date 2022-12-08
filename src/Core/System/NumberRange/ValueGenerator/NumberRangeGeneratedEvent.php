<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\ValueGenerator;

use Shopware\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\Event;

class NumberRangeGeneratedEvent extends Event
{
    public const NAME = 'number_range.generated';

    /**
     * @var string
     */
    private $generatedValue;

    /**
     * @var string
     */
    private $type;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var string|null
     */
    private $salesChannelId;

    /**
     * @var bool
     */
    private $preview;

    public function __construct(string $generatedValue, string $type, Context $context, ?string $salesChannelId, bool $preview = false)
    {
        $this->generatedValue = $generatedValue;
        $this->type = $type;
        $this->context = $context;
        $this->salesChannelId = $salesChannelId;
        $this->preview = $preview;
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
