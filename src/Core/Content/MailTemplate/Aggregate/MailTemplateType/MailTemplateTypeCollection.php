<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                        add(MailTemplateTypeEntity $entity)
 * @method void                        set(string $key, MailTemplateTypeEntity $entity)
 * @method MailTemplateTypeEntity[]    getIterator()
 * @method MailTemplateTypeEntity[]    getElements()
 * @method MailTemplateTypeEntity|null get(string $key)
 * @method MailTemplateTypeEntity|null first()
 * @method MailTemplateTypeEntity|null last()
 */
class MailTemplateTypeCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'mail_template_type_collection';
    }

    protected function getExpectedClass(): string
    {
        return MailTemplateTypeEntity::class;
    }
}
