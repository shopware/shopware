<?php

namespace Shopware\Tests\Rector\Fixture;

class UnrelatedResult
{
    public function first(): ?object
    {
        return null;
    }
}

function skipUnrelatedClass(UnrelatedResult $result): ?object
{
    return $result->first();
}

?>
