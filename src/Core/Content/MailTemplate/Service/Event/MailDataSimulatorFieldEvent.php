<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service\Event;

use Faker\Generator;
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
        private readonly Field $field,
        private readonly Context $context,
        private readonly Generator $faker,
    ) {
    }

    public function getField(): Field
    {
        return $this->field;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getFaker(): Generator
    {
        return $this->faker;
    }

    public function setValue(mixed $value): void
    {
        $this->value = $value;
        $this->hasValue = true;
    }

    public function hasValue(): bool
    {
        return $this->hasValue;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }
}
