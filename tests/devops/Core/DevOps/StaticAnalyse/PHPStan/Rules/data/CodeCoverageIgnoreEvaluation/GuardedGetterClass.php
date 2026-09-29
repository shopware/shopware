<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

/**
 * @codeCoverageIgnore
 */
class GuardedGetterClass
{
    protected string $password = '';

    public function getPassword(): string
    {
        $this->checkIfPropertyAccessIsAllowed('password');

        return $this->password;
    }

    protected function checkIfPropertyAccessIsAllowed(string $property): void
    {
        if ($property === 'password') {
            throw new \RuntimeException('not allowed');
        }
    }
}
