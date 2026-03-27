<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use Psr\Http\Message\UriInterface;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
interface LastRequestAwareHttpClientInterface
{
    public function getLastRequestUri(): ?UriInterface;
}
