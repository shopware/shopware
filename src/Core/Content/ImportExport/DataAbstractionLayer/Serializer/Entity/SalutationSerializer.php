<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Salutation\SalutationDefinition;
use Shopware\Core\System\Salutation\SalutationEntity;
use Symfony\Contracts\Service\ResetInterface;

#[Package('core')]
class SalutationSerializer extends EntitySerializer implements ResetInterface
{
    /**
     * @var array<string>|null[]
     */
    private array $cacheSalutations = [];

    /**
     * @internal
     */
    public function __construct(private readonly EntityRepository $salutationRepository)
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

        if (!isset($deserialized['id']) && isset($deserialized['salutationKey'])) {
            $id = $this->getSalutationId($deserialized['salutationKey']);

            // if we dont find it by salutationKey, only set the id to the fallback if we dont have any other data
            if (!$id && \count($deserialized) === 1) {
                $id = $this->getSalutationId('not_specified');
                unset($deserialized['salutationKey']);
            }

            if ($id) {
                $deserialized['id'] = $id;
            }
        }

        yield from $deserialized;
    }

    public function supports(string $entity): bool
    {
        return $entity === SalutationDefinition::ENTITY_NAME;
    }

    public function reset(): void
    {
        $this->cacheSalutations = [];
    }

    private function getSalutationId(string $salutationKey): ?string
    {
        if (\array_key_exists($salutationKey, $this->cacheSalutations)) {
            return $this->cacheSalutations[$salutationKey];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salutationKey', $salutationKey));
        $salutation = $this->salutationRepository->search($criteria, Context::createDefaultContext())->first();

        $this->cacheSalutations[$salutationKey] = null;
        if ($salutation instanceof SalutationEntity) {
            $this->cacheSalutations[$salutationKey] = $salutation->getId();
        }

        return $this->cacheSalutations[$salutationKey];
    }
}
