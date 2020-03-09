<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\DataAbstractionLayer\Field;

use Shopware\Core\Content\GoogleShopping\DataAbstractionLayer\FieldSerializer\GoogleAccountCredentialFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;

class GoogleAccountCredentialField extends JsonField
{
    protected function getSerializerClass(): string
    {
        return GoogleAccountCredentialFieldSerializer::class;
    }
}
