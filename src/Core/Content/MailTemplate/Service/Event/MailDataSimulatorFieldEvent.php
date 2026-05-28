<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('after-sales')]
class MailDataSimulatorFieldEvent extends Event
{
    private mixed $value = null;

    private bool $hasValue = false;

    public function __construct(
        public readonly Field $field,
        public readonly Context $context,
    ) {
    }

    public function setValue(mixed $value): void
    {
        $this->value = $value;
        $this->hasValue = true;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function hasValue(): bool
    {
        return $this->hasValue;
    }
}
