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
    public mixed $value = null;

    public bool $hasValue = false;

    public function __construct(
        public readonly Field $field,
        public readonly Context $context,
        public readonly Generator $faker,
    ) {
    }

    public function setValue(mixed $value): void
    {
        $this->value = $value;
        $this->hasValue = true;
    }
}
