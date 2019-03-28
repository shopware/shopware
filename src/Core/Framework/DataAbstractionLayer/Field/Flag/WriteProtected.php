<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

class WriteProtected extends Flag
{
    /**
     * @var array[string]bool
     */
    private $allowedScopes = [];

    public function __construct(string ...$allowedScopes)
    {
        foreach ($allowedScopes as $scope) {
            $this->allowedScopes[$scope] = true;
        }
    }

    public function getAllowedScopes(): array
    {
        return array_keys($this->allowedScopes);
    }

    public function isAllowed(string $scope): bool
    {
        return isset($this->allowedScopes[$scope]);
    }

    public function parse(): \Generator
    {
        yield 'write_protected' => [
            array_keys($this->allowedScopes),
        ];
    }
}
