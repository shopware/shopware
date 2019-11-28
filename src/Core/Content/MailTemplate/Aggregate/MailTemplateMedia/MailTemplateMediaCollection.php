<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateMedia;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                         add(MailTemplateMediaEntity $entity)
 * @method void                         set(string $key, MailTemplateMediaEntity $entity)
 * @method MailTemplateMediaEntity[]    getIterator()
 * @method MailTemplateMediaEntity[]    getElements()
 * @method MailTemplateMediaEntity|null get(string $key)
 * @method MailTemplateMediaEntity|null first()
 * @method MailTemplateMediaEntity|null last()
 */
class MailTemplateMediaCollection extends EntityCollection
{
    public function getMailTemplateIds(): array
    {
        return $this->fmap(function (MailTemplateMediaEntity $mailTemplateAttachment) {
            return $mailTemplateAttachment->getMailTemplateId();
        });
    }

    public function filterByMailTemplateId(string $id): self
    {
        return $this->filter(function (MailTemplateMediaEntity $mailTemplateMedia) use ($id) {
            return $mailTemplateMedia->getMailTemplateId() === $id;
        });
    }

    public function getMediaIds(): array
    {
        return $this->fmap(function (MailTemplateMediaEntity $mailTemplateMedia) {
            return $mailTemplateMedia->getMediaId();
        });
    }

    public function filterByMediaId(string $id): self
    {
        return $this->filter(function (MailTemplateMediaEntity $mailTemplateMedia) use ($id) {
            return $mailTemplateMedia->getMediaId() === $id;
        });
    }

    public function getMedia(): MediaCollection
    {
        return new MediaCollection(
            $this->fmap(function (MailTemplateMediaEntity $mailTemplateMedia) {
                return $mailTemplateMedia->getMedia();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return MailTemplateMediaEntity::class;
    }
}
