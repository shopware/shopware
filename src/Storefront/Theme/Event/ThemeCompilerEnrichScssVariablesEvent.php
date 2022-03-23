<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Symfony\Contracts\EventDispatcher\Event;

class ThemeCompilerEnrichScssVariablesEvent extends Event implements ShopwareEvent
{
    private array $variables;

    private string $salesChannelId;

    private Context $context;

    public function __construct(array $variables, string $salesChannelId, Context $context)
    {
        $this->variables = $variables;
        $this->salesChannelId = $salesChannelId;
        $this->context = $context;
    }

    public function addVariable(string $name, string $value, bool $sanitize = false): void
    {
        if ($sanitize) {
            $this->variables[$name] = '\'' . addslashes($value) . '\'';
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

    public function getContext(): Context
    {
        return $this->context;
    }
}
