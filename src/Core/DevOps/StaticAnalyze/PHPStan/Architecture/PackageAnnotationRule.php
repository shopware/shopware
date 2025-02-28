<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\PHPat;
use PHPat\Test\Builder\Rule;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class PackageAnnotationRule
{
    public function testAllClassesHavePackageAnnotation(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::all())
            ->shouldApplyAttribute()
            ->classes(Selector::classname(Package::class))
            ->because('All classes should have a package annotation.');
    }
}
