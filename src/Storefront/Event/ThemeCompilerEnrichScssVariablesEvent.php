<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Feature;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @package storefront
 *
 * @deprecated tag:v6.5.0 - Will be removed. Use Shopware\Storefront\Theme\Event\ThemeCompilerEnrichScssVariablesEvent instead.
 */
#[Package('storefront')]
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
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            sprintf('Class %s is deprecated. Use Shopware\Storefront\Theme\Event\ThemeCompilerEnrichScssVariablesEvent instead.', static::class)
        );

        if ($sanitize) {
            $this->variables[$name] = '\'' . addslashes($value) . '\'';
        } else {
            $this->variables[$name] = $value;
        }
    }

    public function getVariables(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            sprintf('Class %s is deprecated. Use Shopware\Storefront\Theme\Event\ThemeCompilerEnrichScssVariablesEvent instead.', static::class)
        );

        return $this->variables;
    }

    public function getSalesChannelId(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            sprintf('Class %s is deprecated. Use Shopware\Storefront\Theme\Event\ThemeCompilerEnrichScssVariablesEvent instead.', static::class)
        );

        return $this->salesChannelId;
    }
}
