<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Page;

use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Stopwatch\Stopwatch;

class PageLoaderProfiler implements PageLoaderInterface
{
    /**
     * @var PageLoaderInterface
     */
    private $decorated;

    /**
     * @var Stopwatch
     */
    private $stopwatch;

    public function __construct(PageLoaderInterface $decorated, Stopwatch $stopwatch)
    {
        $this->decorated = $decorated;
        $this->stopwatch = $stopwatch;
    }

    public function load(InternalRequest $request, SalesChannelContext $context)
    {
        $this->stopwatch->start(get_class($this->decorated));

        $page = $this->decorated->load($request, $context);

        $this->stopwatch->stop(get_class($this->decorated));

        return $page;
    }
}
