<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Event;

use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Contracts\EventDispatcher\Event;

class DocumentGeneratorCriteriaEvent extends Event
{
    private string $documentType;

    private Criteria $criteria;

    private Context $context;

    /**
     * @var DocumentGenerateOperation[]
     */
    private array $operations;

    public function __construct(string $documentType, array $operations, Criteria $criteria, Context $context)
    {
        $this->documentType = $documentType;
        $this->criteria = $criteria;
        $this->context = $context;
        $this->operations = $operations;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getOperations(): array
    {
        return $this->operations;
    }

    public function getDocumentType(): string
    {
        return $this->documentType;
    }
}
