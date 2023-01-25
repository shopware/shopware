<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Exception;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('core')]
class DecodeByHydratorException extends ShopwareHttpException
{
    public function __construct(Field $field)
    {
        parent::__construct(
            'Decoding of {{ fieldClass }} is handled by the entity hydrator.',
            ['fieldClass' => $field::class]
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__DECODING_HANDLED_BY_ENTITY_HYDRATOR';
    }
}
