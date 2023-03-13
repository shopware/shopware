<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

#[Package('core')]
class IterateEntityIndexerMessage implements AsyncMessageInterface
{
    /**
     * @var string
     */
    protected $indexer;

    /**
     * @internal
     *
     * @deprecated tag:v6.6.0 - parameter $offset will be natively typed to type `?array`
     *
     * @param array{offset: int|null}|null $offset
     * @param list<string> $skip
     */
    public function __construct(
        string $indexer,
        protected $offset,
        protected array $skip = []
    ) {
        $this->indexer = $indexer;
    }

    public function getIndexer(): string
    {
        return $this->indexer;
    }

    /**
     * @deprecated tag:v6.6.0 - reason:return-type-change - return type will be natively typed to type `?array`
     *
     * @return array{offset: int|null}|null
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * @deprecated tag:v6.6.0 - parameter $offset will be natively typed to type `?array`
     *
     * @param array{offset: int|null}|null $offset
     */
    public function setOffset($offset): void
    {
        if ($offset !== null && !\is_array($offset)) {
            Feature::triggerDeprecationOrThrow(
                'v6.6.0.0',
                'The parameter $offset of method ' . __METHOD__ . ' will be natively typed to type `?array` in v6.6.0.0.'
            );
        }

        $this->offset = $offset;
    }

    /**
     * @return list<string>
     */
    public function getSkip(): array
    {
        return $this->skip;
    }
}
