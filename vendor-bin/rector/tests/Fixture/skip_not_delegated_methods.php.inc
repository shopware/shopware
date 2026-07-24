<?php

namespace Shopware\Tests\Rector\Fixture;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

function skipNotDelegatedMethods(EntitySearchResult $result, Entity $entity): int
{
    $result->add($entity);

    return $result->getTotal();
}

?>
