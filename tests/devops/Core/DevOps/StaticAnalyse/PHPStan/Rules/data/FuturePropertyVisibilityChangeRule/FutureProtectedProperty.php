<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\FuturePropertyVisibilityChangeRule;

use Shopware\Core\Framework\Deprecation\BCChange\VisibilityChange;

class FutureProtectedProperty
{
    #[VisibilityChange(version: 'v6.8.0', newVisibility: 'protected')]
    public string $value = 'value';

    public function ownAccess(): string
    {
        return $this->value;
    }
}
