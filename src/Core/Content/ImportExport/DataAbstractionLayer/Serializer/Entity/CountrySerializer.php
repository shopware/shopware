<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Country\CountryEntity;
use Symfony\Contracts\Service\ResetInterface;

#[Package('core')]
class CountrySerializer extends EntitySerializer implements ResetInterface
{
    /**
     * @var array<string>|null[]
     */
    private array $cacheCountries = [];

    /**
     * @internal
     */
    public function __construct(private readonly EntityRepository $countryRepository)
    {
    }

    /**
     * @param array|\Traversable $entity
     *
     * @return array|\Traversable
     */
    public function deserialize(Config $config, EntityDefinition $definition, $entity)
    {
        $deserialized = parent::deserialize($config, $definition, $entity);

        $deserialized = \is_array($deserialized) ? $deserialized : iterator_to_array($deserialized);

        if (!isset($deserialized['id']) && isset($deserialized['iso'])) {
            $id = $this->getCountryId($deserialized['iso']);

            if ($id) {
                $deserialized['id'] = $id;
            }
        }

        yield from $deserialized;
    }

    public function supports(string $entity): bool
    {
        return $entity === CountryDefinition::ENTITY_NAME;
    }

    public function reset(): void
    {
        $this->cacheCountries = [];
    }

    private function getCountryId(string $iso): ?string
    {
        if (\array_key_exists($iso, $this->cacheCountries)) {
            return $this->cacheCountries[$iso];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('iso', $iso));
        $country = $this->countryRepository->search($criteria, Context::createDefaultContext())->first();

        $this->cacheCountries[$iso] = null;
        if ($country instanceof CountryEntity) {
            $this->cacheCountries[$iso] = $country->getId();
        }

        return $this->cacheCountries[$iso];
    }
}
