<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
abstract class AbstractMediaSerializer extends EntitySerializer
{
    abstract public function persistMedia(EntityWrittenEvent $event): void;
}
