<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\ContentSystem;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('framework')]
class StubExtractorEntity extends Entity
{
    public function __construct(string $id)
    {
        $this->setUniqueIdentifier($id);
    }

    public function getApiAlias(): string
    {
        return 'test_entity';
    }
}
