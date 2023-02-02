<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Symfony\Contracts\EventDispatcher\Event;

class ThemeCompilerConcatenatedScriptsEvent extends Event
{
    /**
     * @var string
     */
    private $concatenatedScripts;

    /**
     * @var string
     */
    private $salesChannelId;

    public function __construct(string $concatenatedScripts, string $salesChannelId)
    {
        $this->concatenatedScripts = $concatenatedScripts;
        $this->salesChannelId = $salesChannelId;
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
