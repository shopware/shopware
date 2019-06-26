<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Serializer;

use Shopware\Core\Framework\Api\Exception\UnsupportedEncoderInputException;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Internal;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReadProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Symfony\Component\Serializer\Serializer;

class JsonEntityEncoder
{
    /**
     * @var Serializer
     */
    private $serializer;

    public function __construct(Serializer $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * @param EntityCollection|Entity|null $data
     */
    public function encode(EntityDefinition $definition, $data, string $baseUrl): array
    {
        if ((!$data instanceof EntityCollection) && (!$data instanceof Entity)) {
            throw new UnsupportedEncoderInputException();
        }

        if ($data instanceof EntityCollection) {
            return $this->getDecodedCollection($data, $definition, $baseUrl);
        }

        return $this->getDecodedEntity($data, $definition, $baseUrl);
    }

    private function getDecodedCollection(EntityCollection $collection, EntityDefinition $definition, string $baseUrl): array
    {
        $decoded = [];

        foreach ($collection as $entity) {
            $decoded[] = $this->getDecodedEntity($entity, $definition, $baseUrl);
        }

        return $decoded;
    }

    private function getDecodedEntity(Entity $entity, EntityDefinition $definition, string $baseUrl): array
    {
        $decoded = $this->serializer->normalize($entity);

        return $this->removeProtectedFields($decoded, $definition, $baseUrl);
    }

    private function removeProtectedFields(array $decoded, EntityDefinition $definition, string $baseUrl): array
    {
        $fields = $definition->getFields();

        foreach ($decoded as $key => &$value) {
            $field = $fields->get($key);

            if ($field === null) {
                continue;
            }

            if ($field->getFlag(Internal::class)) {
                unset($decoded[$key]);

                continue;
            }

            /** @var ReadProtected|null $readProtected */
            $readProtected = $field->getFlag(ReadProtected::class);
            if ($readProtected && !$readProtected->isBaseUrlAllowed($baseUrl)) {
                unset($decoded[$key]);

                continue;
            }

            if ($value === null) {
                continue;
            }

            // phpstan would complain if we remove this
            if ($field instanceof AssociationField) {
                if ($field instanceof ManyToOneAssociationField | $field instanceof OneToOneAssociationField) {
                    $value = $this->removeProtectedFields($value, $field->getReferenceDefinition(), $baseUrl);
                }

                if ($field instanceof ManyToManyAssociationField | $field instanceof OneToManyAssociationField) {
                    foreach ($value as $id => $entity) {
                        $value[$id] = $this->removeProtectedFields($entity, $field->getReferenceDefinition(), $baseUrl);
                    }
                }
            }
        }

        return $decoded;
    }
}
