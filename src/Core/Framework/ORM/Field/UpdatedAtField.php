<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Field;

use Shopware\Core\Framework\ORM\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\ORM\Write\EntityExistence;
use Shopware\Core\Framework\ORM\Write\Flag\Required;

class UpdatedAtField extends DateField
{
    public function __construct()
    {
        parent::__construct('updated_at', 'updatedAt');
        $this->setFlags(new Required());
    }

    protected function invoke(EntityExistence $existence, KeyValuePair $data): \Generator
    {
        if (!$existence->exists()) {
            return;
        }

        yield from parent::invoke(
            $existence,
            new KeyValuePair(
                $data->getKey(),
                new \DateTime(),
                $data->isRaw()
            )
        );
    }
}
