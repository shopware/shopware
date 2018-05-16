<?php declare(strict_types=1);

namespace Shopware\System\Mail\Collection;

use Shopware\Framework\ORM\EntityCollection;
use Shopware\System\Mail\Struct\MailBasicStruct;

class MailBasicCollection extends EntityCollection
{
    /**
     * @var MailBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? MailBasicStruct
    {
        return parent::get($id);
    }

    public function current(): MailBasicStruct
    {
        return parent::current();
    }

    public function getOrderStateIds(): array
    {
        return $this->fmap(function (MailBasicStruct $mail) {
            return $mail->getOrderStateId();
        });
    }

    public function filterByOrderStateId(string $id): self
    {
        return $this->filter(function (MailBasicStruct $mail) use ($id) {
            return $mail->getOrderStateId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return MailBasicStruct::class;
    }
}
