<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange\ValueGenerator;

use Symfony\Component\EventDispatcher\Event;

class NumberRangeGeneratedEvent extends Event
{
    public const NAME = 'number_range.generated';

    /**
     * @var string
     */
    private $generatedValue;

    public function __construct(string $generatedValue)
    {
        $this->generatedValue = $generatedValue;
    }

    public function getGeneratedValue(): string
    {
        return $this->generatedValue;
    }

    public function setGeneratedValue(string $generatedValue): void
    {
        $this->generatedValue = $generatedValue;
    }
}
