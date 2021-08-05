<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field;

use Shopware\Core\Content\ImportExport\Exception\InvalidIdentifierException;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Computed;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;

class FieldSerializer extends AbstractFieldSerializer
{
    public function serialize(Config $config, Field $field, $value): iterable
    {
        $key = $field->getPropertyName();

        if ($field instanceof ManyToManyAssociationField && $value !== null) {
            $referenceIdField = $field->getReferenceField();
            $ids = implode('|', array_map(static function ($e) use ($referenceIdField) {
                if ($e instanceof Entity) {
                    return $e->getUniqueIdentifier();
                }
                if (\is_array($e)) {
                    return $e[$referenceIdField];
                }

                return null;
            }, \is_array($value) ? $value : iterator_to_array($value)));

            yield $key => $ids;

            return;
        }

        if ($field instanceof AssociationField) {
            return;
        }

        if ($field instanceof TranslatedField) {
            return;
        }

        if ($field->getFlag(Computed::class)) {
            return;
        }

        if ($field instanceof DateField || $field instanceof DateTimeField) {
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format(Defaults::STORAGE_DATE_TIME_FORMAT);
            }

            if (empty($value)) {
                return null;
            }

            yield $key => (string) $value;
        } elseif ($field instanceof BoolField) {
            yield $key => $value === true ? '1' : '0';
        } elseif ($field instanceof JsonField) {
            yield $key => $value === null ? null : json_encode($value);
        } else {
            $value = $value === null ? $value : (string) $value;
            yield $key => $value;
        }
    }

    public function deserialize(Config $config, Field $field, $value)
    {
        if ($value === null) {
            return null;
        }

        if ($field->is(Computed::class) || $field->is(Runtime::class)) {
            return null;
        }

        /** @var WriteProtected|null $writeProtection */
        $writeProtection = $field->getFlag(WriteProtected::class);
        if ($writeProtection && !$writeProtection->isAllowed(Context::SYSTEM_SCOPE)) {
            return null;
        }

        if ($field instanceof ManyToManyAssociationField) {
            return array_filter(
                array_map(
                    function ($id) {
                        $id = $this->normalizeId($id);
                        if ($id === '') {
                            return null;
                        }

                        return ['id' => $id];
                    },
                    explode('|', $value)
                )
            );
        }

        if ($field instanceof OneToManyAssociationField) {
            // early return in case a specific serializer has already hydrated associations
            if (\is_array($value)) {
                return null;
            }

            return array_filter(
                array_map(
                    function ($id) {
                        $id = $this->normalizeId($id);
                        if ($id === '') {
                            return null;
                        }

                        return $id;
                    },
                    explode('|', $value)
                )
            );
        }

        if ($field instanceof AssociationField) {
            return null;
        }

        if ($field instanceof TranslatedField) {
            return null;
        }

        if (\is_string($value) && $value === '') {
            return null;
        }

        if ($field instanceof DateField || $field instanceof DateTimeField) {
            return new \DateTimeImmutable($value);
        }

        if ($field instanceof BoolField) {
            $value = mb_strtolower($value);

            return !($value === '0' || $value === 'false' || $value === 'n' || $value === 'no');
        }

        if ($field instanceof JsonField) {
            return json_decode($value, true);
        }

        if ($field instanceof IntField) {
            return (int) $value;
        }

        if ($field instanceof IdField || $field instanceof FkField) {
            return $this->normalizeId($value);
        }

        return $value;
    }

    public function supports(Field $field): bool
    {
        return true;
    }

    private function normalizeId(?string $id): string
    {
        $id = mb_strtolower(trim((string) $id));

        if (!Feature::isActive('FEATURE_NEXT_8097') || Uuid::isValid($id) || $id === '') {
            return $id;
        }

        if (str_contains($id, '|')) {
            throw new InvalidIdentifierException($id);
        }

        return Uuid::fromStringToHex($id);
    }
}
