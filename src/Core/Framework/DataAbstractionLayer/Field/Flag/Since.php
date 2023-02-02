<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

class Since extends Flag
{
    /**
     * @var string
     */
    private $since;

    public function __construct(string $since)
    {
        $this->since = $since;
    }

    public function parse(): \Generator
    {
        yield 'since' => $this->since;
    }

    public function getSince(): string
    {
        return $this->since;
    }
}
