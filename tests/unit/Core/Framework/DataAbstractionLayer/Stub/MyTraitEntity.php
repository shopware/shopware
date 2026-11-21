<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Stub;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;

/**
 * @internal
 */
class MyTraitEntity extends Entity
{
    use EntityCustomFieldsTrait;

    /**
     * @param array<string, mixed>|null $customFields
     * @param array<string, mixed> $translated
     */
    public function __construct(
        string $_uniqueIdentifier,
        ?array $customFields = [],
        array $translated = [],
    ) {
        $this->_uniqueIdentifier = $_uniqueIdentifier;
        $this->customFields = $customFields;
        $this->translated = $translated;
    }
}
