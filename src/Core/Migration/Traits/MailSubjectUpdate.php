<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Traits;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class MailSubjectUpdate
{
    /**
     * @var string
     */
    protected $type;

    /**
     * @var string|null
     */
    protected $enSubject;

    /**
     * @var string|null
     */
    protected $deSubject;

    public function __construct(
        string $type,
        ?string $enSubject = null,
        ?string $deSubject = null
    ) {
        $this->type = $type;
        $this->enSubject = $enSubject;
        $this->deSubject = $deSubject;
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
