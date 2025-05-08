<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Traits;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class MailSubjectUpdate
{
    public function __construct(
        protected string $type,
        protected ?string $enSubject = null,
        protected ?string $deSubject = null
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getDeSubject(): ?string
    {
        return $this->deSubject;
    }

    public function getEnSubject(): ?string
    {
        return $this->enSubject;
    }
}
