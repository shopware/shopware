<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateMedia;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<MailTemplateMediaEntity>
 */
class MailTemplateMediaCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
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

    /**
     * @return list<string>
     */
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

    public function getApiAlias(): string
    {
        return 'mail_template_media_collection';
    }

    protected function getExpectedClass(): string
    {
        return MailTemplateMediaEntity::class;
    }
}
