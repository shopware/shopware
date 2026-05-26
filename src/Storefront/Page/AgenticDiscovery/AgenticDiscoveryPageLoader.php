<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AgenticDiscovery;

use Shopware\Core\Framework\AgenticDiscovery\AgenticDiscoveryDocumentType;
use Shopware\Core\Framework\AgenticDiscovery\Manifest\AgenticManifestBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_DISCOVERY
 *
 * @internal
 */
#[Package('framework')]
class AgenticDiscoveryPageLoader
{
    public function __construct(
        private readonly AgenticManifestBuilder $manifestBuilder,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function load(AgenticDiscoveryDocumentType $type, Request $request, Context $context): ?AgenticDiscoveryPage
    {
        $manifest = $this->manifestBuilder->buildForRequest($type, $request);
        if ($manifest === null) {
            return null;
        }

        $page = new AgenticDiscoveryPage($type, $manifest);

        $this->eventDispatcher->dispatch(new AgenticDiscoveryPageLoadedEvent($page, $context, $request));

        return $page;
    }
}
