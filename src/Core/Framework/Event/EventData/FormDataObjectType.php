<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventData;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class FormDataObjectType extends ObjectType
{
    final public const MARKER = 'formData';

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [self::MARKER => true]);
    }
}
