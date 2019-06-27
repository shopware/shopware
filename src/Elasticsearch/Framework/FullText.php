<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

class FullText
{
    /**
     * @var string
     */
    protected $fullText;

    /**
     * @var string
     */
    protected $boosted;

    public function __construct(string $fullText, string $boosted)
    {
        $this->fullText = $fullText;
        $this->boosted = $boosted;
    }

    public function getFullText(): string
    {
        return $this->fullText;
    }

    public function getBoosted(): string
    {
        return $this->boosted;
    }
}
