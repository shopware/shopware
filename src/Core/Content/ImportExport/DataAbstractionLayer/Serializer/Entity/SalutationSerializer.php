<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Salutation\SalutationDefinition;
use Shopware\Core\System\Salutation\SalutationEntity;

class SalutationSerializer extends EntitySerializer
{
    /**
     * @var EntityRepositoryInterface
     */
    private $salutationRepository;

    /**
     * @var string[]
     */
    private $salutations = [];

    public function __construct(EntityRepositoryInterface $salutationRepository)
    {
        $this->salutationRepository = $salutationRepository;
    }

    public function deserialize(Config $config, EntityDefinition $definition, $record)
    {
        $deserialized = parent::deserialize($config, $definition, $record);

        if (is_iterable($deserialized)) {
            $deserialized = iterator_to_array($deserialized);
        }

        if (!isset($deserialized['id']) && isset($deserialized['salutationKey'])) {
            $id = $this->getSalutationId($deserialized['salutationKey']);

            // if we dont find it by salutationKey, only set the id to the fallback if we dont have any other data
            if (!$id && count($deserialized) === 1) {
                $id = $this->getSalutationId('not_specified');
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

    private function getSalutationId(string $salutationKey): ?string
    {
        if (empty($this->salutations)) {
            $salutations = $this->salutationRepository->search(new Criteria(), Context::createDefaultContext());

            /** @var SalutationEntity $salutation */
            foreach ($salutations as $salutation) {
                $this->salutations[$salutation->getSalutationKey()] = $salutation->getId();
            }
        }

        return $this->salutations[$salutationKey] ?? null;
    }
}
