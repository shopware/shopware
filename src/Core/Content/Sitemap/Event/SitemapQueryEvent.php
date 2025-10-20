<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Event;

use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Framework\Event\GenericEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('discovery')]
final class SitemapQueryEvent extends Event implements GenericEvent
{
    public function __construct(
        private readonly QueryBuilder $query,
        private readonly SalesChannelContext $context,
        private readonly int $limit,
        private readonly ?int $offset,
        private readonly string $name,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getQuery(): QueryBuilder
    {
        return $this->query;
    }

    public function getContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getOffset(): ?int
    {
        return $this->offset;
    }
}
