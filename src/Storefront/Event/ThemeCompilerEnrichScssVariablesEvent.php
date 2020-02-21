<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Symfony\Contracts\EventDispatcher\Event;

class ThemeCompilerEnrichScssVariablesEvent extends Event
{
    /**
     * @var array
     */
    private $variables;

    /**
     * @var string
     */
    private $salesChannelId;

    public function __construct(array $variables, string $salesChannelId)
    {
        $this->variables = $variables;
        $this->salesChannelId = $salesChannelId;
    }

    public function addVariable(string $name, string $value, bool $useQuotes = false): void
    {
        if ($useQuotes) {
            $this->variables[$name] = '\'' . $value . '\'';
        } else {
            $this->variables[$name] = $value;
        }
    }

    public function getVariables(): array
    {
        return $this->variables;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }
}
