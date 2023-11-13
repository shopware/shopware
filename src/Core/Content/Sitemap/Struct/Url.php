<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('sales-channel')]
class Url extends Struct implements \Stringable
{
    /**
     * The Url
     */
    private string $loc;

    /**
     * Date and time of last modification
     */
    private \DateTimeInterface $lastmod;

    /**
     * Frequency of changing
     */
    private string $changefreq;

    /**
     * Relative priority for this URL
     */
    private float $priority = 0.5;

    private string $resource;

    private string $identifier;

    public function __toString(): string
    {
        return sprintf(
            '<url><loc>%s</loc><lastmod>%s</lastmod><changefreq>%s</changefreq><priority>%.1f</priority></url>',
            $this->getLoc(),
            $this->getLastmod()->format('Y-m-d'),
            $this->getChangefreq(),
            $this->getPriority()
        );
    }

    public function getLoc(): string
    {
        return $this->loc;
    }

    public function setLoc(string $loc): void
    {
        $this->loc = $loc;
    }

    public function getLastmod(): \DateTimeInterface
    {
        return $this->lastmod;
    }

    public function setLastmod(\DateTimeInterface $lastmod): void
    {
        $this->lastmod = $lastmod;
    }

    public function getChangefreq(): string
    {
        return $this->changefreq;
    }

    public function setChangefreq(string $changefreq): void
    {
        $this->changefreq = $changefreq;
    }

    public function getPriority(): float
    {
        return $this->priority;
    }

    public function setPriority(float $priority): void
    {
        $this->priority = $priority;
    }

    public function setResource(string $resource): void
    {
        $this->resource = $resource;
    }

    public function getResource(): string
    {
        return $this->resource;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getApiAlias(): string
    {
        return 'sitemap_url';
    }
}
