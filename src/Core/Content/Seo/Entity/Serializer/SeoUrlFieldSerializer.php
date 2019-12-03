<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\Entity\Serializer;

use Shopware\Core\Content\Seo\Entity\Field\SeoUrlAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\OneToManyAssociationFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;

class SeoUrlFieldSerializer extends OneToManyAssociationFieldSerializer
{
    public function encode(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): \Generator
    {
        if (!$field instanceof SeoUrlAssociationField) {
            throw new InvalidSerializerFieldException(SeoUrlAssociationField::class, $field);
        }

        $seoUrls = $data->getValue();
        foreach ($seoUrls as $i => $seoUrl) {
            $seoUrl['routeName'] = $field->getRouteName();
            $seoUrl['isModified'] = true;

            $seoUrls[$i] = $seoUrl;
        }

        $data = new KeyValuePair($data->getKey(), $seoUrls, $data->isRaw());

        return parent::encode($field, $existence, $data, $parameters);
    }
}
