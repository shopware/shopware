<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\_helper;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
final class TestExtractorEntity extends Entity
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
