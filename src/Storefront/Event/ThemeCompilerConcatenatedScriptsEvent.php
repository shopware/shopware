<?php declare(strict_types=1);

namespace Shopware\Storefront\Event;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @deprecated tag:v6.7.0 - Will be removed because it is not used anymore. The concatenation of scripts is not happening anymore.
 */
#[Package('storefront')]
class ThemeCompilerConcatenatedScriptsEvent extends Event
{
    public function __construct(
        private string $concatenatedScripts,
        private readonly string $salesChannelId
    ) {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0')
        );
    }

    public function getConcatenatedScripts(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.7.0.0')
        );

        return $this->concatenatedScripts;
    }

    public function setConcatenatedScripts(string $concatenatedScripts): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.7.0.0')
        );

        $this->concatenatedScripts = $concatenatedScripts;
    }

    public function getSalesChannelId(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.7.0.0')
        );

        return $this->salesChannelId;
    }
}
