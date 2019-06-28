<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Writer;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

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

    public function __construct(EntityRepositoryInterface $repository, Context $context)
    {
        $this->repository = $repository;
        $this->context = $context;
        $this->buffer = [];
    }

    public function append(array $data, int $index): void
    {
        $this->buffer[] = $data;
    }

    public function flush(): void
    {
        if (!empty($this->buffer)) {
            $this->repository->create($this->buffer, $this->context);
        }
        $this->buffer = [];
    }

    public function finish(): void
    {
        $this->flush();
    }
}
