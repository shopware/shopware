<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class WriteProtected extends Flag
{
    /**
     * @var array<string, bool>
     */
    private array $allowedScopes = [];

    private bool $allowWriteThroughAdminApi = false;

    public function __construct(string ...$allowedScopes)
    {
        foreach ($allowedScopes as $scope) {
            $this->allowedScopes[$scope] = true;
        }
    }

    /**
     * @return list<string>
     */
    public function getAllowedScopes(): array
    {
        return array_keys($this->allowedScopes);
    }

    public function isAllowed(string $scope): bool
    {
        return isset($this->allowedScopes[$scope]);
    }

    /**
     * Allows writes through Admin API endpoints that replace the default DAL API controller with more specific permission handling.
     *
     * @see \Shopware\Core\Framework\Api\Controller\UserController
     * @see \Shopware\Core\Framework\Api\Controller\IntegrationController
     */
    public function allowWriteThroughAdminApi(): self
    {
        $this->allowWriteThroughAdminApi = true;

        return $this;
    }

    /**
     * @return \Generator<string, list<list<string>>>
     */
    public function parse(): \Generator
    {
        if ($this->allowWriteThroughAdminApi) {
            // Admin API writes are authorized by their dedicated controller, so this flag is omitted from the entity schema and enforced only during DAL writes.
            return;
        }

        yield 'write_protected' => [
            array_keys($this->allowedScopes),
        ];
    }
}
