<?php declare(strict_types=1);

namespace Shopware\Core\System\Mail;

use Shopware\Core\Framework\ORM\EntityCollection;

class MailCollection extends EntityCollection
{
    /**
     * @var MailStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? MailStruct
    {
        return parent::get($id);
    }

    public function current(): MailStruct
    {
        return parent::current();
    }

    public function getOrderStateIds(): array
    {
        return $this->fmap(function (MailStruct $mail) {
            return $mail->getOrderStateId();
        });
    }

    public function filterByOrderStateId(string $id): self
    {
        return $this->filter(function (MailStruct $mail) use ($id) {
            return $mail->getOrderStateId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return MailStruct::class;
    }
}
