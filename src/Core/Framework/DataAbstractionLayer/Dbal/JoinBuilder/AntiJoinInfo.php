<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\JoinBuilder;

use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;

/**
 * @deprecated tag:v6.4.0 - Will be removed
 */
class AntiJoinInfo
{
    /**
     * @var AssociationField[]
     */
    private $associations;

    /**
     * @var array
     */
    private $additionalSelects;

    /**
     * @var string
     */
    private $condition;

    public function __construct(array $associations, string $condition, array $additionalSelects)
    {
        foreach ($associations as $association) {
            if (!$association instanceof AssociationField) {
                throw new \InvalidArgumentException('Expected $associations to be an array of ' . AssociationField::class . ' got ' . \get_class($association));
            }
        }

        $this->associations = $associations;
        $this->condition = $condition;
        $this->additionalSelects = $additionalSelects;
    }

    /**
     * @return AssociationField[]
     */
    public function getAssociations(): array
    {
        return $this->associations;
    }

    public function getCondition(): string
    {
        return $this->condition;
    }

    public function getAdditionalSelects(): array
    {
        return $this->additionalSelects;
    }
}
