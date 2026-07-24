<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use Shopware\Core\Framework\Log\Package;

/**
 * Enforces the version-range policy for composer requirements: caret by default, tilde or an
 * exact pin for the packages where minor/patch updates have repeatedly broken the pipeline.
 *
 * @internal
 */
#[Package('framework')]
class ComposerVersionConstraints
{
    private const PACKAGE_EXCEPTIONS = [
        '~' => [
            '^symfony\/.*$' => 'We are too tightly coupled to symfony, therefore minor updates often cause breaks',
            '^php$' => 'PHP does not follow semantic versioning, therefore minor updates include breaks',
            '^doctrine\/dbal$' => 'Minor updates often introduce deprecations, which cause PHPStan to fail.',
            '^dompdf\/dompdf$' => 'Minor updates of dompdf may change rendered documents, therefore only patch updates are allowed.',
        ],
        'strict' => [
            '^phpstan\/phpstan.*$' => 'Even patch updates for PHPStan may lead to a red CI pipeline, because of new static analysis errors',
            '^friendsofphp\/php-cs-fixer$' => 'Even patch updates for PHP-CS-Fixer may lead to a red CI pipeline, because of new style issues',
            '^symplify\/phpstan-rules$' => 'Even patch updates for PHPStan plugins may lead to a red CI pipeline, because of new static analysis errors',
            '^rector\/type-perfect$' => 'Even patch updates for PHPStan plugins may lead to a red CI pipeline, because of new static analysis errors',
            '^phpat\/phpat$' => 'Even patch updates for PHPStan plugins may lead to a red CI pipeline, because of new static analysis errors',
            '^scssphp\/scssphp$' => 'Patch updates of scssphp might lead to UI breaks, therefore it is pinned.',
            '^shopware\/conflicts$' => 'The shopware conflicts packages should be required in any version, so use `*` constraint',
            '^shopware\/core$' => 'The shopware core packages should be required in any version, so use `*` constraint, the version constraint will be automatically synced during the release process',
            '^ext-.*$' => 'PHP extension version ranges should be required in any version, so use `*` constraint',
        ],
    ];

    public function __invoke(Context $context): void
    {
        $composerFiles = $context->platform->pullRequest->getFiles()->matches('**/composer.json');

        if ($root = $context->platform->pullRequest->getFiles()->matches('composer.json')->first()) {
            $composerFiles->add($root);
        }

        foreach ($composerFiles as $composerFile) {
            $composerFileName = $composerFile->name;

            if ($composerFile->status === File::STATUS_REMOVED
                || str_starts_with($composerFileName, 'tests/')
                || str_contains($composerFileName, '/test/')
                || str_contains($composerFileName, '/Test/')
            ) {
                continue;
            }

            $composerContent = json_decode($composerFile->getContent(), true);
            $requirements = array_merge(
                $composerContent['require'] ?? [],
            );

            foreach ($requirements as $package => $constraint) {
                if (str_contains($package, 'polyfill')) {
                    continue;
                }

                $this->checkConstraint($context, $composerFile, (string) $package, (string) $constraint);
            }
        }
    }

    private function checkConstraint(Context $context, File $composerFile, string $package, string $constraint): void
    {
        foreach (self::PACKAGE_EXCEPTIONS['~'] as $exceptionPackage => $exceptionMessage) {
            if (preg_match('/' . $exceptionPackage . '/', $package)) {
                if (!str_contains($constraint, '~')) {
                    $context->failure(
                        \sprintf(
                            'The package `%s` from composer file `%s` should use the [tilde version range](https://getcomposer.org/doc/articles/versions.md#tilde-version-range-) to only allow patch version updates. ',
                            $package,
                            $composerFile->name
                        ) . $exceptionMessage
                    );
                }

                return;
            }
        }

        foreach (self::PACKAGE_EXCEPTIONS['strict'] as $exceptionPackage => $exceptionMessage) {
            if (preg_match('/' . $exceptionPackage . '/', $package)) {
                if (str_contains($constraint, '~') || str_contains($constraint, '^')) {
                    $context->failure(
                        \sprintf(
                            'The package `%s` from composer file `%s` should be pinned to a specific version. ',
                            $package,
                            $composerFile->name
                        ) . $exceptionMessage
                    );
                }

                return;
            }
        }

        if (!str_contains($constraint, '^')) {
            $context->failure(
                \sprintf(
                    'The package `%s` from composer file `%s` should use the [caret version range](https://getcomposer.org/doc/articles/versions.md#caret-version-range-), to automatically allow minor updates.',
                    $package,
                    $composerFile->name
                )
            );
        }
    }
}
