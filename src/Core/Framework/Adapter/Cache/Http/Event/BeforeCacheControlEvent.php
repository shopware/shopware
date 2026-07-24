<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Http\Event;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched before cache control headers are modified.
 * Listeners can prevent cache control modification by calling skipCacheControl().
 *
 * @deprecated tag:v6.8.0 - Will be removed without replacement together with dispatching code.
 */
#[Package('framework')]
class BeforeCacheControlEvent extends Event
{
    private bool $skipCacheControl = false;

    public function __construct(
        public readonly Request $request,
        public readonly Response $response
    ) {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0')
        );
    }

    /**
     * Call this method to prevent cache control header modification.
     */
    public function skipCacheControl(): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0')
        );

        $this->skipCacheControl = true;
    }

    public function shouldSkipCacheControl(): bool
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0')
        );

        return $this->skipCacheControl;
    }
}
