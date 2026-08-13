<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Version\Cleanup;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('framework')]
class CleanupVersionEvent extends Event
{
    /**
     * @param list<string> $protectedVersionIds
     */
    public function __construct(
        private readonly \DateTimeInterface $cleanupTime,
        private array $protectedVersionIds = [],
    ) {
    }

    public function getCleanupTime(): \DateTimeInterface
    {
        return $this->cleanupTime;
    }

    public function addProtectedVersionId(string $versionId): void
    {
        if (!\in_array($versionId, $this->protectedVersionIds, true)) {
            $this->protectedVersionIds[] = $versionId;
        }
    }

    /**
     * @return list<string>
     */
    public function getProtectedVersionIds(): array
    {
        return $this->protectedVersionIds;
    }
}
