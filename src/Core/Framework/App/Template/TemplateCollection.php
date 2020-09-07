<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Template;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                       add(TemplateEntity $entity)
 * @method void                       set(string $key, TemplateEntity $entity)
 * @method \Generator<TemplateEntity> getIterator()
 * @method array<TemplateEntity>      getElements()
 * @method TemplateEntity|null        get(string $key)
 * @method TemplateEntity|null        first()
 * @method TemplateEntity|null        last()
 */
class TemplateCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return TemplateEntity::class;
    }
}
