<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateMedia;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<MailTemplateMediaEntity>
 */
#[Package('sales-channel')]
class MailTemplateMediaCollection extends EntityCollection
{
    /**
     * @return list<string>
     */
    public function getMailTemplateIds(): array
    {
        return $this->fmap(fn (MailTemplateMediaEntity $mailTemplateAttachment) => $mailTemplateAttachment->getMailTemplateId());
    }

    public function filterByMailTemplateId(string $id): self
    {
        return $this->filter(fn (MailTemplateMediaEntity $mailTemplateMedia) => $mailTemplateMedia->getMailTemplateId() === $id);
    }

    /**
     * @return list<string>
     */
    public function getMediaIds(): array
    {
        return $this->fmap(fn (MailTemplateMediaEntity $mailTemplateMedia) => $mailTemplateMedia->getMediaId());
    }

    public function filterByMediaId(string $id): self
    {
        return $this->filter(fn (MailTemplateMediaEntity $mailTemplateMedia) => $mailTemplateMedia->getMediaId() === $id);
    }

    public function getMedia(): MediaCollection
    {
        return new MediaCollection(
            $this->fmap(fn (MailTemplateMediaEntity $mailTemplateMedia) => $mailTemplateMedia->getMedia())
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
