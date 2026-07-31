<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Type;

use Shopware\Core\Checkout\DocumentV2\DocumentFormat;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Framework\App\Aggregate\AppDocumentType\AppDocumentTypeCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Loads document types registered by active apps via the `app_document_type` aggregate.
 *
 * @internal
 */
#[Package('after-sales')]
final class AppDocumentTypeLoader implements ResetInterface
{
    /**
     * @var array<string, list<string>>|null
     */
    private ?array $typesByName = null;

    /**
     * @var array<string, array<string, scalar>>|null
     */
    private ?array $configByName = null;

    /**
     * @param EntityRepository<AppDocumentTypeCollection> $appDocumentTypeRepository
     */
    public function __construct(private readonly EntityRepository $appDocumentTypeRepository)
    {
    }

    /**
     * @return array<string, list<string>>
     */
    public function load(): array
    {
        $this->fetch();

        \assert($this->typesByName !== null);

        return $this->typesByName;
    }

    /**
     * @return array<string, scalar>
     */
    public function loadConfig(string $technicalName): array
    {
        $this->fetch();

        \assert($this->configByName !== null);

        return $this->configByName[$technicalName] ?? [];
    }

    public function reset(): void
    {
        $this->typesByName = null;
        $this->configByName = null;
    }

    private function fetch(): void
    {
        if ($this->typesByName !== null) {
            return;
        }

        $criteria = (new Criteria())->addFilter(new EqualsFilter('app.active', true));

        $appDocumentTypes = $this->appDocumentTypeRepository
            ->search($criteria, Context::createDefaultContext())
            ->getEntities();

        $validFormats = array_column(DocumentFormat::cases(), 'value');

        $typesByName = [];
        $configByName = [];

        foreach ($appDocumentTypes as $appDocumentType) {
            $technicalName = $appDocumentType->getTechnicalName();

            // should never shadow core type
            if (DocumentType::tryFrom($technicalName) !== null) {
                continue;
            }

            /** @var list<string> $declaredFormats */
            $declaredFormats = $appDocumentType->getFormats() ?? [];
            $formats = array_values(array_intersect($declaredFormats, $validFormats));

            if ($formats !== []) {
                $typesByName[$technicalName] = $formats;
            }

            /** @var array<string, scalar> $config */
            $config = $appDocumentType->getConfig() ?? [];

            $configByName[$technicalName] = $config;
        }

        $this->typesByName = $typesByName;
        $this->configByName = $configByName;
    }
}
