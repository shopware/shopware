<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;

class TreePathField extends LongTextField
{
    private $pathField;

    public function __construct(string $storageName, string $propertyName, string $pathField = 'id')
    {
        $this->pathField = $pathField;
        parent::__construct($storageName, $propertyName);

        $this->addFlags(new WriteProtected(Context::SYSTEM_SCOPE));
    }

    public function getPathField(): string
    {
        return $this->pathField;
    }
}
