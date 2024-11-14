<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Migration;

use PHPStan\Analyser\Scope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
trait InMigrationClassTrait
{
    protected function isInMigrationClass(Scope $scope): bool
    {
        if (!$scope->isInClass()) {
            return false;
        }

        return $scope->getClassReflection()->isSubclassOf(MigrationStep::class);
    }
}
