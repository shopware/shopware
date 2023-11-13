<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('sales-channel')]
class Sitemap extends Struct
{
    /**
     * @var string
     */
    protected $filename;

    /**
     * @var \DateTimeInterface
     */
    protected $created;

    /**
     * @throws \Exception
     */
    public function __construct(
        string $filename,
        private int $urlCount,
        ?\DateTimeInterface $created = null
    ) {
        $this->filename = $filename;
        $this->created = $created ?: new \DateTime('NOW', new \DateTimeZone('UTC'));
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): void
    {
        $this->filename = $filename;
    }

    public function getUrlCount(): int
    {
        return $this->urlCount;
    }

    public function setUrlCount(int $urlCount): void
    {
        $this->urlCount = $urlCount;
    }

    public function getCreated(): \DateTimeInterface
    {
        return $this->created;
    }

    public function setCreated(\DateTimeInterface $created): void
    {
        $this->created = $created;
    }

    public function getApiAlias(): string
    {
        return 'sitemap';
    }
}
