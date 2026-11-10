<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\Tests;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStan\Type\FileTypeMapper;
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
                    'Drop the @var annotation: a search carries the collection type, the generic is inferred from it.',
                    28,
                ],
                [
                    'Drop the @var annotation: a search carries the collection type, the generic is inferred from it.',
                    38,
                ],
                [
                    'Use StaticEntityRepository::of(ProductCollection::class, ...) instead of the @var annotation: no search carries the collection type.',
                    46,
                ],
                [
                    'Use StaticEntityRepository::of(ProductCollection::class, ...) instead of the @var annotation: no search carries the collection type.',
                    54,
                ],
                [
                    'Use StaticEntityRepository::of(ProductCollection::class, ...) instead of the @var annotation: no search carries the collection type.',
                    62,
                ],
                [
                    'Drop the @var annotation: a search carries the collection type, the generic is inferred from it.',
                    112,
                ],
                [
                    'Use StaticEntityRepository::of(ProductCollection::class, ...) instead of the @var annotation: no search carries the collection type.',
                    123,
                ],
            ],
        );
    }

    protected function getRule(): Rule
    {
        return new StaticEntityRepositoryVarAnnotationRule(self::getContainer()->getByType(FileTypeMapper::class));
    }
}
