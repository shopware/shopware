<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\WriteProtected;
use Shopware\Core\Framework\SourceContext;

class TreePathField extends LongTextField
{
    private $pathField;

    public function __construct(string $storageName, string $propertyName, string $pathField = 'id')
    {
        $this->pathField = $pathField;
        parent::__construct($storageName, $propertyName);

        $this->addFlags(new WriteProtected(SourceContext::ORIGIN_SYSTEM));
    }

    public function getPathField(): string
    {
        return $this->pathField;
    }
}
