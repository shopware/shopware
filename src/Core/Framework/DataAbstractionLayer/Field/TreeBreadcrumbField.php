<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;

class TreeBreadcrumbField extends JsonField
{
    /**
     * @var string
     */
    private $nameField;

    public function __construct(string $storageName = 'breadcrumb', string $propertyName = 'breadcrumb', string $nameField = 'name')
    {
        $this->nameField = $nameField;
        parent::__construct($storageName, $propertyName);

        $this->addFlags(new WriteProtected(Context::SYSTEM_SCOPE));
    }

    public function getNameField(): string
    {
        return $this->nameField;
    }
}
