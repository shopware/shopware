<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * @package storefront
 */
class ThemeCompilerConcatenatedScriptsEvent extends Event
{
    public function __construct(private string $concatenatedScripts, private readonly string $salesChannelId)
    {
    }

    public function getConcatenatedScripts(): string
    {
        return $this->concatenatedScripts;
    }

    public function setConcatenatedScripts(string $concatenatedScripts): void
    {
        $this->concatenatedScripts = $concatenatedScripts;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }
}
