<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Requirement;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Requirement\Exception\RequirementException;
use Shopware\Core\Framework\Plugin\Requirement\Exception\RequirementStackException;

#[Package('core')]
class RequirementExceptionStack
{
    /**
     * @var RequirementException[]
     */
    private array $exceptions = [];

    public function add(RequirementException ...$exceptions): void
    {
        foreach ($exceptions as $exception) {
            $this->exceptions[] = $exception;
        }
    }

    public function tryToThrow(string $method): void
    {
        $exceptions = $this->exceptions;
        $this->exceptions = [];

        if ($exceptions) {
            throw new RequirementStackException($method, ...$exceptions);
        }
    }
}
