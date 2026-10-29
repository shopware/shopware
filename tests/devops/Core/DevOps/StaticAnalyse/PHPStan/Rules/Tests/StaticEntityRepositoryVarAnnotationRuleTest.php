<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\Tests;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\StaticEntityRepositoryVarAnnotationRule;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<StaticEntityRepositoryVarAnnotationRule>
 */
#[Package('framework')]
class StaticEntityRepositoryVarAnnotationRuleTest extends RuleTestCase
{
    public function testRule(): void
    {
        $this->analyse(
            [__DIR__ . '/../data/StaticEntityRepositoryVarAnnotation/static-entity-repository-var-annotation-cases.php'],
            [
                [
                    'Drop the @var annotation on this StaticEntityRepository: a search carries the collection type, the generic is inferred from it.',
                    27,
                ],
                [
                    'Drop the @var annotation on this StaticEntityRepository: a search carries the collection type, the generic is inferred from it.',
                    37,
                ],
                [
                    'Bind this StaticEntityRepository with StaticEntityRepository::of(ProductCollection::class, ...) instead of a @var annotation: no search carries the collection type.',
                    45,
                ],
                [
                    'Bind this StaticEntityRepository with StaticEntityRepository::of(ProductCollection::class, ...) instead of a @var annotation: no search carries the collection type.',
                    53,
                ],
                [
                    'Bind this StaticEntityRepository with StaticEntityRepository::of(ProductCollection::class, ...) instead of a @var annotation: no search carries the collection type.',
                    61,
                ],
            ],
        );
    }

    protected function getRule(): Rule
    {
        return new StaticEntityRepositoryVarAnnotationRule();
    }
}
