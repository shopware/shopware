<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class DocumentConfigurationService
{
    /**
     * @var EntityRepositoryInterface
     */
    private $documentConfigRepository;

    public function __construct(EntityRepositoryInterface $documentConfigRepository)
    {
        $this->documentConfigRepository = $documentConfigRepository;
    }

    public function getConfiguration(Context $context, string $documentTypeId, ?array $specificConfiguration): DocumentConfiguration
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('documentTypeId', $documentTypeId));
        /** @var DocumentBaseConfigEntity $typeConfig */
        $typeConfig = $this->documentConfigRepository->search($criteria, $context)->first();

        return DocumentConfigurationFactory::createConfiguration($specificConfiguration, $typeConfig);
    }
}
