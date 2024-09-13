<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Service;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Part\DataPart;

#[Package('services-settings')]
class SendMailTemplateParams extends Struct
{
    /**
     * @param array<Address> $recipients
     * @param array<string, mixed> $data
     * @param array<DataPart> $attachments
     */
    public function __construct(
        public string $mailTemplateId,
        public string $languageId,
        public array $recipients,
        public array $data,
        public array $attachments = [],
        public ?string $salesChannelId = null
    ) {
    }
}
