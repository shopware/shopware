<?php declare(strict_types=1);

namespace Shopware\Core\System\Event;

use Symfony\Component\EventDispatcher\Event;

class NumberRangeGeneratorEndEvent extends Event
{
    public const NAME = 'number_range_generator.end';

    /**
     * @var string
     */
    protected $generatedValue;

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
