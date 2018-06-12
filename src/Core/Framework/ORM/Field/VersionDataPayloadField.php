<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Field;

use Shopware\Core\Framework\ORM\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\ORM\Write\EntityExistence;

/**
 * @internal
 */
class VersionDataPayloadField extends JsonField
{
    /**
     * This field is used by the VersionCommitData only. It just pipes the given input
     * to the storage as we don't want magic to happen here.
     *
     * @param EntityExistence $existence
     * @param KeyValuePair $data
     *
     * @return \Generator
     */
    public function __invoke(EntityExistence $existence, KeyValuePair $data): \Generator
    {
        yield $this->storageName => $data->getValue();
    }
}
