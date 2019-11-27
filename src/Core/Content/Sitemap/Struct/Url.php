<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Struct;

use Shopware\Core\Framework\Struct\Struct;

class Url extends Struct
{
    /**
     * The Url
     *
     * @var string
     */
    private $loc;

    /**
     * Date and time of last modification
     *
     * @var \DateTimeInterface
     */
    private $lastmod;

    /**
     * Frequency of changing
     *
     * @var string
     */
    private $changefreq;

    /**
     * Relative priority for this URL
     *
     * @var float
     */
    private $priority = 0.5;

    /**
     * @var string
     */
    private $resource;

    /**
     * @var string
     */
    private $identifier;

    /**
     * @return string
     */
    public function __toString()
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
}
