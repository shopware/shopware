<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;

class LanguageParentFkField extends FkField
{
    public function __construct(string $referenceClass)
    {
        parent::__construct('language_parent_id', 'languageParentId', $referenceClass);
        $this->setFlags(new Required());
    }
}
