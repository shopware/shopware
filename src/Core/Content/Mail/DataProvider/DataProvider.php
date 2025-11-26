<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\DataProvider;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
interface DataProvider
{
    public function supports(string $entityName): bool;

    public function getData(string $entityId, Context $context): Entity;
}
