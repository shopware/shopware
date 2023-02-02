<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Template;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 *
 * @extends EntityCollection<TemplateEntity>
 */
class TemplateCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return TemplateEntity::class;
    }
}
