<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Version\Cleanup;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class CleanupVersionEvent
{
    /**
     * @param array<string, true> $protectedVersionIds
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
        $this->protectedVersionIds[$versionId] = true;
    }

    /**
     * @return list<string>
     */
    public function getProtectedVersionIds(): array
    {
        return \array_keys($this->protectedVersionIds);
    }
}
