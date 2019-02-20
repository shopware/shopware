<?php declare(strict_types=1);

namespace Shopware\Core\System\Event;

use Symfony\Component\EventDispatcher\Event;

class NumberRangeGeneratorStartEvent extends Event
{
    public const NAME = 'number_range_generator.start';

    /**
     * @var array
     */
    protected $parsedPattern;

    public function __construct(?array $parsedPattern)
    {
        $this->parsedPattern = $parsedPattern;
    }

    public function getParsedPattern(): array
    {
        return $this->parsedPattern;
    }

    public function setParsedPattern(array $parsedPattern): void
    {
        $this->parsedPattern = $parsedPattern;
    }
}
