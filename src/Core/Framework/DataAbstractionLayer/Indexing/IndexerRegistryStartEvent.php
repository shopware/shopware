<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing;

use Shopware\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\Event;

class IndexerRegistryStartEvent extends Event
{
    /**
     * @var \DateTimeInterface
     */
    private $timestamp;

    /**
     * @var Context|null
     */
    private $context;

    public function __construct(\DateTimeInterface $timestamp, ?Context $context = null)
    {
        $this->timestamp = $timestamp;
        $this->context = $context;
    }

    public function getTimestamp(): \DateTimeInterface
    {
        return $this->timestamp;
    }

    public function getContext(): ?Context
    {
        return $this->context;
    }
}
