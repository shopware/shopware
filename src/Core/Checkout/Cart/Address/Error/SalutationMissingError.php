<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Address\Error;

use Shopware\Core\Checkout\Cart\Error\Error;

class SalutationMissingError extends Error
{
    private const KEY = 'salutation-missing';

    /**
     * @var string
     */
    private $name;

    /**
     * @var string|null
     */
    private $entityId;

    public function __construct(string $name, ?string $entityId = null)
    {
        $this->name = $name;
        $this->entityId = $entityId;

        $this->message = sprintf(
            'A salutation needs to be specified for %s.',
            $name
        );

        parent::__construct($this->message);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function blockOrder(): bool
    {
        return true;
    }

    public function getKey(): string
    {
        return sprintf('%s-%s', self::KEY, $this->name);
    }

    public function getLevel(): int
    {
        return self::LEVEL_NOTICE;
    }

    public function getMessageKey(): string
    {
        return self::KEY;
    }

    public function getId(): string
    {
        return $this->getKey();
    }

    public function getParameters(): array
    {
        return ['name' => $this->name, 'entityId' => $this->entityId];
    }
}
