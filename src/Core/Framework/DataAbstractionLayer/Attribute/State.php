<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Attribute;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field as DalField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @phpstan-type StateData array{machine: string, scopes: array<string>, api: bool|array{admin-api: bool, store-api: bool}, column: string|null, nullable: bool, type: string, translated: bool}
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class State extends Field
{
    public const TYPE = 'state';

    /**
     * @param array<string> $scopes
     */
    public function __construct(
        public string $machine,
        public array $scopes = [Context::SYSTEM_SCOPE],
        bool|array $api = false,
        ?string $column = null
    ) {
        parent::__construct(type: self::TYPE, api: $api, column: $column);
    }

    public static function fromArray(array $data): State
    {
        /** @var array<string> $scopes */
        $scopes = $data['scopes'];

        $instance = new State(
            (string) $data['machine'],
            $scopes,
            $data['api'],
            $data['column']
        );
        $instance->nullable = (bool) $data['nullable'];

        return $instance;
    }

    public function toDefinition(): Definition
    {
        $definition = new Definition(self::class);
        $definition->setFactory([self::class, 'fromArray']);
        $definition->setArguments([
            [
                'machine' => $this->machine,
                'scopes' => $this->scopes,
                'api' => $this->api,
                'column' => $this->column,
                'nullable' => $this->nullable,
                'type' => $this->type,
                'translated' => $this->translated,
            ],
        ]);

        return $definition;
    }

    public function createField(string $propertyName, string $column, string $entityName, ?string $propertyType = null): DalField
    {
        return new StateMachineStateField($this->column ?? $column, $propertyName, $this->machine, $this->scopes);
    }

    public function getFieldClass(): string
    {
        return StateMachineStateField::class;
    }
}
