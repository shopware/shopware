<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;

/**
 * @internal
 */
class EmptyEntityExistence extends EntityExistence
{
    public function __construct()
    {
        parent::__construct('', [], true, false, false, []);
    }
}
