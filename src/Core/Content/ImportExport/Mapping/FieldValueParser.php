<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\Mapping;

use Shopware\Core\Content\ImportExport\Exception\MappingException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextWithHtmlField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;

class FieldValueParser
{
    /**
     * @var EntityDefinition
     */
    private $entityDefinition;

    public function __construct(EntityDefinition $entityDefinition)
    {
        $this->entityDefinition = $entityDefinition;
    }

    public function parse(Field $field, $value)
    {
        if ($value === null) {
            return null;
        }

        // Complex field types

        if ($field instanceof TranslatedField) {
            $localizedField = $this->entityDefinition->getTranslationDefinition()->getFields()->get($field->getPropertyName());
            if (!is_array($value)) {
                throw new MappingException('TranslatedField requires value to be array');
            }
            $result = [];
            foreach ($value as $locale => $localized) {
                $result[$locale] = $this->parse($localizedField, $localized);
            }

            return $result;
        }

        if ($field instanceof ManyToManyAssociationField || $field instanceof OneToManyAssociationField) {
            if (!is_array($value)) {
                throw new MappingException('To-Many association field requires value to be array');
            }

            if (!isset($value['id']) || !is_string($value['id'])) {
                throw new MappingException('To-Many association field can only accept pipe-separated IDs');
            }

            $result = [];
            foreach (explode('|', $value['id']) as $id) {
                if (mb_strlen($id) > 0) {
                    $result[] = ['id' => $id];
                }
            }

            return $result;
        }

        if ($field instanceof ManyToOneAssociationField || $field instanceof OneToOneAssociationField) {
            if (!is_array($value)) {
                throw new MappingException('To-One association field requires value to be array', ['field' => $field->getPropertyName(), 'value' => $value]);
            }

            return $value;
        }

        if ($field instanceof JsonField) {
            if (!is_array($value)) {
                throw new MappingException('JsonField requires value to be array');
            }
            $result = [];
            foreach ($field->getPropertyMapping() as $subField) {
                if (!array_key_exists($subField->getPropertyName(), $value)) {
                    continue;
                }
                $result[$subField->getPropertyName()] = $this->parse($subField, $value[$subField->getPropertyName()]);
            }

            return $result;
        }

        // Scalar field types
        switch (true) {
            case $field instanceof FloatField:
                return (float) $value;
            case $field instanceof BoolField:
                return (bool) $value;
            case $field instanceof IntField:
                return (int) $value;
            case $field instanceof StringField:
            case $field instanceof LongTextField:
            case $field instanceof LongTextWithHtmlField:
            case $field instanceof FkField:
                return (string) $value;
            case $field instanceof DateField:
                return !empty($value) ? new \DateTimeImmutable($value) : null;
        }

        return $value;
    }
}
