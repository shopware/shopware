<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class PropertyNotFoundException extends DataAbstractionLayerException
{
    public function __construct(string $property, string $entityClassName)
    {
        parent::__construct(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::PROPERTY_NOT_FOUND,
            'Property "{{ property }}" does not exist in entity "{{ entityClassName }}".',
            ['property' => $property, 'entityClassName' => $entityClassName]
        );
    }
}
