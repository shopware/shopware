<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Writer;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;

class RepositoryWriter implements WriterInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var array
     */
    private $buffer;

    /**
     * @var string|null
     */
    private $identityField;

    public function __construct(EntityRepositoryInterface $repository, Context $context, ?string $identityField = null)
    {
        $this->repository = $repository;
        $this->context = $context;
        $this->buffer = [];
        $this->identityField = $identityField;
    }

    public function append(array $data, int $index): void
    {
        $this->buffer[] = $data;
    }

    public function flush(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        if ($this->identityField !== null && $this->identityField !== 'id') {
            $this->enrichRecordsWithIds();
        }

        $this->repository->upsert($this->buffer, $this->context);
        $this->buffer = [];
    }

    public function finish(): void
    {
        $this->flush();
    }

    private function enrichRecordsWithIds(): void
    {
        $filters = [];
        foreach ($this->buffer as $record) {
            if (!isset($record['id']) && isset($record[$this->identityField])) {
                $filters[] = new EqualsFilter($this->identityField, $record[$this->identityField]);
            }
        }

        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, $filters));

        $searchResult = $this->repository->search($criteria, $this->context);

        $idMap = [];
        /** @var Entity $entity */
        foreach ($searchResult->getEntities() as $entity) {
            $key = $entity->get($this->identityField);
            $idMap[$key] = $entity->get('id');
        }

        foreach ($this->buffer as $key => $record) {
            if (isset($record[$this->identityField]) && isset($idMap[$record[$this->identityField]])) {
                $this->buffer[$key]['id'] = $idMap[$record[$this->identityField]];
            }
        }
    }
}
