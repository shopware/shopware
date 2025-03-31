<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Aggregate\SnippetSetLocale;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Locale\LocaleEntity;

#[Package('services-settings')]
class SnippetSetLocaleEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $name;

    public function getLocale(): ?LocaleEntity
    {
        return $this->locale;
    }

    public function setLocale(?LocaleEntity $locale): void
    {
        $this->locale = $locale;
    }
}
