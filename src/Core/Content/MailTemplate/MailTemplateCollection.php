<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                    add(MailTemplateEntity $entity)
 * @method void                    set(string $key, MailTemplateEntity $entity)
 * @method MailTemplateEntity[]    getIterator()
 * @method MailTemplateEntity[]    getElements()
 * @method MailTemplateEntity|null get(string $key)
 * @method MailTemplateEntity|null first()
 * @method MailTemplateEntity|null last()
 */
class MailTemplateCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return MailTemplateEntity::class;
    }
}
