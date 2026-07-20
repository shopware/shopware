<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;
use Shopware\Core\Framework\MessageQueue\DeduplicatableMessageInterface;
use Shopware\Core\Framework\Util\Hasher;

#[Package('framework')]
class FullEntityIndexerMessage implements AsyncMessageInterface, DeduplicatableMessageInterface
{
    /**
     * @internal
     *
     * @param list<string> $skip
     * @param list<string> $only
     */
    public function __construct(
        protected array $skip = [],
        protected array $only = []
    ) {
    }

    /**
     * @return list<string>
     */
    public function getSkip(): array
    {
        return $this->skip;
    }

    /**
     * @return list<string>
     */
    public function getOnly(): array
    {
        return $this->only;
    }

    /**
     * @experimental stableVersion:v6.8.0 feature:DEDUPLICATABLE_MESSAGES
     */
    public function deduplicationId(): ?string
    {
        $sortedSkip = $this->skip;
        sort($sortedSkip);

        $sortedOnly = $this->only;
        sort($sortedOnly);

        $data = json_encode([
            $sortedSkip,
            $sortedOnly,
        ]);

        if ($data === false) {
            return null;
        }

        return Hasher::hash($data);
    }
}
