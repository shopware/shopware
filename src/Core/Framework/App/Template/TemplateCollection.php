<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Template;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @extends EntityCollection<TemplateEntity>
 */
#[Package('core')]
class TemplateCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return TemplateEntity::class;
    }
}
