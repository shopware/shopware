<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Field;

use Shopware\Core\Framework\ORM\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\ORM\Write\EntityExistence;
use Shopware\Core\Framework\ORM\Write\Flag\Required;

class CreatedAtField extends DateField
{
    public function __construct()
    {
        parent::__construct('created_at', 'createdAt');
        $this->setFlags(new Required());
    }

    protected function invoke(EntityExistence $existence, KeyValuePair $data): \Generator
    {
        if ($existence->exists()) {
            return;
        }

        if (!$data->getValue()) {
            $data = new KeyValuePair(
                $data->getKey(),
                new \DateTime(),
                $data->isRaw()
            );
        }

        yield from parent::invoke($existence, $data);
    }
}
