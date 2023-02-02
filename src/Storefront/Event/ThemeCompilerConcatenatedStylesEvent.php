<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Symfony\Contracts\EventDispatcher\Event;

class ThemeCompilerConcatenatedStylesEvent extends Event
{
    /**
     * @var string
     */
    private $concatenatedStyles;

    /**
     * @var string
     */
    private $salesChannelId;

    public function __construct(string $concatenatedStyles, string $salesChannelId)
    {
        $this->concatenatedStyles = $concatenatedStyles;
        $this->salesChannelId = $salesChannelId;
    }

    public function getConcatenatedStyles(): string
    {
        return $this->concatenatedStyles;
    }

    public function setConcatenatedStyles(string $concatenatedStyles): void
    {
        $this->concatenatedStyles = $concatenatedStyles;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }
}
