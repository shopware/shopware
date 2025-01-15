<?php declare(strict_types=1);

use Danger\Config;
use Danger\Context;
use Danger\Platform\Github\Github;
use Danger\Rule\Condition;
use Danger\Struct\File;

const COMPOSER_PACKAGE_EXCEPTIONS = [
    '~' => [
        '^symfony\/.*$' => 'We are too tightly coupled to symfony, therefore minor updates often cause breaks',
        '^php$' => 'PHP does not follow semantic versioning, therefore minor updates include breaks',
    ],
    'strict' => [
        '^phpstan\/phpstan.*$' => 'Even patch updates for PHPStan may lead to a red CI pipeline, because of new static analysis errors',
        '^phpstan\/phpdoc-parser.*$' => 'Even patch updates for PHPStan plugins may lead to a red CI pipeline, because of no lock on their side',
        '^friendsofphp\/php-cs-fixer$' => 'Even patch updates for PHP-CS-Fixer may lead to a red CI pipeline, because of new style issues',
        '^symplify\/phpstan-rules$' => 'Even patch updates for PHPStan plugins may lead to a red CI pipeline, because of new static analysis errors',
        '^rector\/type-perfect$' => 'Even patch updates for PHPStan plugins may lead to a red CI pipeline, because of new static analysis errors',
        '^phpat\/phpat$' => 'Even patch updates for PHPStan plugins may lead to a red CI pipeline, because of new static analysis errors',
        '^dompdf\/dompdf$' => 'Patch updates of dompdf have let to a lot of issues in the past, therefore it is pinned.',
        '^scssphp\/scssphp$' => 'Patch updates of scssphp might lead to UI breaks, therefore it is pinned.',
        '^shopware\/conflicts$' => 'The shopware conflicts packages should be required in any version, so use `*` constraint',
        '^shopware\/core$' => 'The shopware core packages should be required in any version, so use `*` constraint, the version constraint will be automatically synced during the release process',
        '^ext-.*$' => 'PHP extension version ranges should be required in any version, so use `*` constraint',
    ],
];

const BaseTestClasses = [
    'RuleTestCase',
    'TestCase',
    'MiddlewareTestCase',
];

return (new Config())
    ->useThreadOn(Config::REPORT_LEVEL_WARNING)
    ->useRule(function (Context $context): void {
        $files = $context->platform->pullRequest->getFiles();

        if ($files->matches('changelog/_unreleased/*.md')->count() === 0) {
            $context->warning('The Pull Request doesn\'t contain any changelog file');
        }
    })

    ->useRule(new Condition(
        function (Context $context) {
            $labels = array_map('strtolower', $context->platform->pullRequest->labels);

            return !\in_array('skip-danger-phpstan-baseline', $labels, true);
        },
        [
            function (Context $context): void {
                $filesWithIgnoredErrors = [];
                $phpstanBaseline = $context->platform->pullRequest->getFile('phpstan-baseline.neon')->getContent();
                foreach ($context->platform->pullRequest->getFiles()->map(fn (File $f) => $f->name) as $fileName) {
                    if (str_contains($phpstanBaseline, 'path: ' . $fileName)) {
                        $filesWithIgnoredErrors[] = $fileName;
                    }
                }

                if ($filesWithIgnoredErrors) {
                    $context->failure(
                        'Some files you touched in your MR contain ignored PHPStan errors. Please be nice and fix all ignored errors for the following files:<br>'
                        . implode('<br>', $filesWithIgnoredErrors)
                    );
                }
            },
            function (Context $context): void {
                $files = $context->platform->pullRequest->getFiles();

                $newRepoUseInFrontend = array_merge(
                    $files->filterStatus(File::STATUS_MODIFIED)->matches('src/Storefront/Controller/*')
                        ->matchesContent('/EntityRepository/')
                        ->matchesContent('/^((?!@deprecated).)*$/')->getElements(),
                    $files->filterStatus(File::STATUS_MODIFIED)->matches('src/Storefront/Page/*')
                        ->matchesContent('/EntityRepository/')
                        ->matchesContent('/^((?!@deprecated).)*$/')->getElements(),
                    $files->filterStatus(File::STATUS_MODIFIED)->matches('src/Storefront/Pagelet/*')
                        ->matchesContent('/EntityRepository/')
                        ->matchesContent('/^((?!@deprecated).)*$/')->getElements(),
                );

                if (count($newRepoUseInFrontend) > 0) {
                    $errorFiles = [];
                    foreach ($newRepoUseInFrontend as $file) {
                        if ($file->name !== '.danger.php') {
                            $errorFiles[] = $file->name . '<br/>';
                        }
                    }

                    if (count($errorFiles) === 0) {
                        return;
                    }

                    $context->failure(
                        'Do not use direct repository calls in the Frontend Layer (Controller, Page, Pagelet).'
                        . ' Use Store-Api Routes instead.<br/>'
                        . implode('<br>', $errorFiles)
                    );
                }
            },
        ]
    ))
    ->useRule(function (Context $context): void {
        $files = $context->platform->pullRequest->getFiles();

        if ($files->matches('*/shopware.yaml')->count() > 0) {
            $context->warning('You updated the shopware.yaml, please consider to update the config-schema.json');
        }
    })
    ->useRule(function (Context $context): void {
        function checkMigrationForBundle(string $bundle, Context $context): void
        {
            $files = $context->platform->pullRequest->getFiles();

            $migrationFiles = $files->filterStatus(File::STATUS_ADDED)->matches(sprintf('src/%s/Migration/V*/Migration*.php', $bundle));
            $migrationTestFiles = $files->filterStatus(File::STATUS_ADDED)->matches(sprintf('tests/migration/%s/V*/*.php', $bundle));

            if ($migrationFiles->count() && !$migrationTestFiles->count()) {
                $context->failure('Please add tests for your new Migration file');
            }
        }

        checkMigrationForBundle('Administration', $context);
        checkMigrationForBundle('Core', $context);
        checkMigrationForBundle('Elasticsearch', $context);
        checkMigrationForBundle('Storefront', $context);
    })
    ->useRule(function (Context $context): void {
        $newSqlHeredocs = $context->platform->pullRequest->getFiles()->filterStatus(File::STATUS_MODIFIED)->matchesContent('/<<<SQL/');

        if ($newSqlHeredocs->count() <= 0) {
            return;
        }

        $errorFiles = [];
        foreach ($newSqlHeredocs as $file) {
            if ($file->name !== '.danger.php') {
                $errorFiles[] = $file->name . '<br/>';
            }
        }

        if (count($errorFiles) === 0) {
            return;
        }

        $context->failure(
            'Please use [Nowdoc](https://www.php.net/manual/de/language.types.string.php#language.types.string.syntax.nowdoc)'
            . ' for SQL (&lt;&lt;&lt;\'SQL\') instead of Heredoc (&lt;&lt;&lt;SQL)<br/>'
            . implode('<br>', $errorFiles)
        );
    })
    ->useRule(function (Context $context): void {
        $changedTemplates = $context->platform->pullRequest->getFiles()
            ->filterStatus(File::STATUS_MODIFIED)
            ->matches('src/Storefront/Resources/views/*.twig')
            ->getElements();

        if (count($changedTemplates) <= 0) {
            return;
        }

        $patched = [];
        foreach ($changedTemplates as $file) {
            preg_match_all('/-.*?(\{% block (.*?) %})+/', $file->patch, $removedBlocks);
            preg_match_all('/\+.*?(\{% block (.*?) %})+/', $file->patch, $addedBlocks);
            if (!isset($removedBlocks[2]) || !is_array($removedBlocks[2])) {
                $removedBlocks[2] = [];
            }
            if (!isset($addedBlocks[2]) || !is_array($addedBlocks[2])) {
                $addedBlocks[2] = [];
            }

            $remaining = array_diff_assoc($removedBlocks[2], $addedBlocks[2]);

            if (count($remaining) > 0) {
                foreach ($remaining as $item) {
                    $patched[] = $item;
                }
            }
        }

        if (count($patched) === 0) {
            return;
        }

        $context->warning(
            'You probably moved or deleted a twig block. This is likely a hard break. Please check your template'
            . ' changes and make sure that deleted blocks are already deprecated.<br/>'
            . 'If you are sure everything is fine with your changes, you can resolve this warning.<br/>'
            . 'Moved or deleted block:<br/>'
            . implode('<br>', $patched)
        );
    })
    ->useRule(function (Context $context): void {
        $invalidFiles = [];

        foreach ($context->platform->pullRequest->getFiles() as $file) {
            if (str_starts_with($file->name, '.run/')) {
                continue;
            }

            if ($file->status !== File::STATUS_REMOVED && preg_match('/^([-+\.\w\/]+)$/', $file->name) === 0) {
                $invalidFiles[] = $file->name;
            }
        }

        if (count($invalidFiles) > 0) {
            $context->failure(
                'The following filenames contain invalid special characters, please use only alphanumeric characters, dots, dashes and underscores:<br/>'
                . implode('<br>', $invalidFiles)
            );
        }
    })
    ->useRule(function (Context $context): void {
        $addedFiles = $context->platform->pullRequest->getFiles()->filterStatus(File::STATUS_ADDED);

        $addedLegacyTests = [];

        foreach ($addedFiles->matches('src/**/*Test.php') as $file) {
            if (str_contains($file->name, 'src/WebInstaller/')) {
                continue;
            }

            $content = $file->getContent();

            if (str_contains($content, 'extends TestCase')) {
                $addedLegacyTests[] = $file->name;
            }
        }

        if (count($addedLegacyTests) > 0) {
            $context->failure(
                'Don\'t add new testcases in the `/src` folder, for new tests write "real" unit tests under `tests/unit` and if needed a few meaningful integration tests under `tests/integration`:<br/>'
                . implode('<br>', $addedLegacyTests)
            );
        }
    })
    ->useRule(function (Context $context): void {
        $addedUnitTests = $context->platform->pullRequest->getFiles()
            ->filter(fn (File $file) => in_array($file->status, [File::STATUS_ADDED, File::STATUS_MODIFIED], true))
            ->matches('tests/unit/**/*Test.php');

        $addedSrcFiles = $context->platform->pullRequest->getFiles()->filterStatus(File::STATUS_ADDED)->matches('src/**/*.php');
        $missingUnitTests = [];
        $unitTestsName = [];

        // prepare phpunit code coverage exclude lists
        $phpUnitConfig = __DIR__ . '/phpunit.xml.dist';
        $excludedDirs = [];
        $excludedFiles = [];
        $dom = new DOMDocument();

        if ($dom->load($phpUnitConfig)) {
            $xpath = new DOMXPath($dom);
            foreach ($xpath->query('//source/exclude/directory') as $dirDomElement) {
                $excludedDirs[] = [
                    'path' => rtrim($dirDomElement->nodeValue, '/') . '/',
                    'suffix' => $dirDomElement->getAttribute('suffix') ?: '',
                ];
            }

            foreach ($xpath->query('//source/exclude/file') as $fileDomElements) {
                $excludedFiles[] = $fileDomElements->nodeValue;
            }
        } else {
            $context->warning(sprintf('Was not able to load phpunit config file %s. Please check configuration.', $phpUnitConfig));
        }

        foreach ($addedUnitTests as $file) {
            $content = $file->getContent();

            preg_match('/\s+extends\s+(?<class>\w+)/', $content, $matches);

            if (isset($matches['class']) && in_array($matches['class'], BaseTestClasses, true)) {
                $fqcn = str_replace('.php', '', $file->name);
                $className = explode('/', $fqcn);

                $unitTestsName[] = end($className);
            }
        }

        foreach ($addedSrcFiles as $file) {
            $content = $file->getContent();

            $fqcn = str_replace('.php', '', $file->name);
            $className = explode('/', $fqcn);
            $class = end($className);

            if (\str_contains($content, '* @codeCoverageIgnore')) {
                continue;
            }

            if (\str_contains($content, 'abstract class ' . $class)) {
                continue;
            }

            if (\str_contains($content, 'interface ' . $class)) {
                continue;
            }

            if (\str_contains($content, 'trait ' . $class)) {
                continue;
            }

            if (\str_starts_with($class, 'Migration1')) {
                continue;
            }

            // process phpunit code coverage exclude lists
            if (in_array($file->name, $excludedFiles, true)) {
                continue;
            }

            $dir = dirname($file->name);
            $fileName = basename($file->name);

            foreach ($excludedDirs as $excludedDir) {
                if (str_starts_with($dir, $excludedDir['path']) && str_ends_with($fileName, $excludedDir['suffix'])) {
                    continue 2;
                }
            }

            $ignoreSuffixes = [
                'Entity',
                'Collection',
                'Struct',
                'Field',
                'Test',
                'Definition',
                'Event',
            ];

            $ignored = false;

            foreach ($ignoreSuffixes as $ignoreSuffix) {
                if (\str_ends_with($class, $ignoreSuffix)) {
                    $ignored = true;

                    break;
                }
            }

            if (!$ignored && !\in_array($class . 'Test', $unitTestsName, true)) {
                $missingUnitTests[] = $file->name;
            }
        }

        if (\count($missingUnitTests) > 0) {
            $context->warning(
                'Please be kind and add unit tests for your new code in these files: <br/><br/>'
                . implode('<br/>', $missingUnitTests)
                . '<br/><br/>If you are sure everything is fine with your changes, you can resolve this warning. <br /> You can run `composer make:coverage` to generate dummy unit tests for files that are not covered'
            );
        }
    })
    // check for composer version operators
    ->useRule(function (Context $context): void {
        $composerFiles = $context->platform->pullRequest->getFiles()->matches('**/composer.json');

        if ($root = $context->platform->pullRequest->getFiles()->matches('composer.json')->first()) {
            $composerFiles->add($root);
        }

        foreach ($composerFiles as $composerFile) {
            if ($composerFile->status === File::STATUS_REMOVED || str_contains((string) $composerFile->name, 'src/WebInstaller') || str_contains((string) $composerFile->name, 'src/Core/DevOps/StaticAnalyze/PHPStan')) {
                continue;
            }

            $composerContent = json_decode($composerFile->getContent(), true);
            $requirements = array_merge(
                $composerContent['require'] ?? [],
                $composerContent['require-dev'] ?? []
            );

            foreach ($requirements as $package => $constraint) {
                if (str_contains($package, 'polyfill')) {
                    continue;
                }

                foreach (COMPOSER_PACKAGE_EXCEPTIONS['~'] as $exceptionPackage => $exceptionMessage) {
                    if (preg_match('/' . $exceptionPackage . '/', $package)) {
                        if (!str_contains($constraint, '~')) {
                            $context->failure(
                                sprintf(
                                    'The package `%s` from composer file `%s` should use the [tilde version range](https://getcomposer.org/doc/articles/versions.md#tilde-version-range-) to only allow patch version updates. ',
                                    $package,
                                    $composerFile->name
                                ) . $exceptionMessage
                            );
                        }

                        continue 2;
                    }
                }

                foreach (COMPOSER_PACKAGE_EXCEPTIONS['strict'] as $exceptionPackage => $exceptionMessage) {
                    if (preg_match('/' . $exceptionPackage . '/', $package)) {
                        if (str_contains($constraint, '~') || str_contains($constraint, '^')) {
                            $context->failure(
                                sprintf(
                                    'The package `%s` from composer file `%s` should be pinned to a specific version. ',
                                    $package,
                                    $composerFile->name
                                ) . $exceptionMessage
                            );
                        }

                        continue 2;
                    }
                }

                if (!str_contains($constraint, '^')) {
                    $context->failure(
                        sprintf(
                            'The package `%s` from composer file `%s` should use the [caret version range](https://getcomposer.org/doc/articles/versions.md#caret-version-range-), to automatically allow minor updates.',
                            $package,
                            $composerFile->name
                        )
                    );
                }
            }
        }
    })
;
