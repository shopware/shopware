<?php declare(strict_types=1);

namespace Shopware\Api\Mail\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Mail\Struct\MailBasicStruct;

class MailBasicCollection extends EntityCollection
{
    /**
     * @var MailBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? MailBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): MailBasicStruct
    {
        return parent::current();
    }

    public function getOrderStateUuids(): array
    {
        return $this->fmap(function (MailBasicStruct $mail) {
            return $mail->getOrderStateUuid();
        });
    }

    public function filterByOrderStateUuid(string $uuid): self
    {
        return $this->filter(function (MailBasicStruct $mail) use ($uuid) {
            return $mail->getOrderStateUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return MailBasicStruct::class;
    }
}
