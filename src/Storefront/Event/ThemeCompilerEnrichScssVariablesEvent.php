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

    public function addVariable(string $name, string $value, bool $sanitize = false): void
    {
        if ($sanitize) {
            $this->variables[$name] = '\'' . filter_var($value, \FILTER_SANITIZE_STRING) . '\'';
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
