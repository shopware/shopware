<?php declare(strict_types=1);

use Danger\Config;
use Danger\Context;
use Danger\Platform\Github\Github;
use Danger\Platform\Gitlab\Gitlab;
use Danger\Rule\CommitRegex;
use Danger\Rule\Condition;
use Danger\Rule\DisallowRepeatedCommits;
use Danger\Struct\File;
use Danger\Struct\Gitlab\File as GitlabFile;

const COMPOSER_PACKAGE_EXCEPTIONS = [
    '~' => [
        '^symfony\/.*$' => 'We are too tightly coupled to symfony, therefore minor updates often cause breaks',
        '^php$' => 'PHP does not follow semantic versioning, therefore minor updates include breaks',
    ],
    'strict' => [
        '^phpstan\/phpstan.*$' => 'Even patch updates for PHPStan may lead to a red CI pipeline, because of new static analysis errors',
        '^friendsofphp\/php-cs-fixer$' => 'Even patch updates for PHP-CS-Fixer may lead to a red CI pipeline, because of new style issues',
        '^symplify\/phpstan-rules$'  => 'Even patch updates for PHPStan plugins may lead to a red CI pipeline, because of new static analysis errors',
        '^rector\/type-perfect$'  => 'Even patch updates for PHPStan plugins may lead to a red CI pipeline, because of new static analysis errors',
        '^tomasvotruba\/type-coverage$'  => 'Even patch updates for PHPStan plugins may lead to a red CI pipeline, because of new static analysis errors',
        '^phpat\/phpat$'  => 'Even patch updates for PHPStan plugins may lead to a red CI pipeline, because of new static analysis errors',
        '^dompdf\/dompdf$' => 'Patch updates of dompdf have let to a lot of issues in the past, therefore it is pinned.',
        '^scssphp\/scssphp$' => 'Patch updates of scssphp might lead to UI breaks, therefore it is pinned.',
        '^shopware\/conflicts$' => 'The shopware conflicts packages should be required in any version, so use `*` constraint',
        '^shopware\/core$' => 'The shopware core packages should be required in any version, so use `*` constraint, the version constraint will be automatically synced during the release process',
        '^ext-.*$' => 'PHP extension version ranges should be required in any version, so use `*` constraint',
    ],
];

const BaseTestClasses = [
    'RuleTestCase',
    'TestCase'
];

return (new Config())
    ->useThreadOn(Config::REPORT_LEVEL_WARNING)
    ->useRule(new DisallowRepeatedCommits())
    ->useRule(function (Context $context): void {
        $files = $context->platform->pullRequest->getFiles();

        if ($files->matches('changelog/_unreleased/*.md')->count() === 0) {
            $context->warning('The Pull Request doesn\'t contain any changelog file');
        }
    })
    ->useRule(new Condition(
        function (Context $context) {
            return $context->platform instanceof Gitlab;
        },
        [
            function (Context $context): void {
                $labels = array_map('strtolower', $context->platform->pullRequest->labels);

                if ($context->platform->raw['squash'] === true && in_array('github', $labels, true)) {
                    $context->failure('GitHub PRs are not allowed to be squashed');
                }
            },
        ]
    ))
    /**
     * MRs that target a release branch that is not trunk should have a thread with link to a trunk MR
     * to disable this rule you can add the no-trunk label
     */
    ->useRule(new Condition(
        function (Context $context) {
            $labels = array_map('strtolower', $context->platform->pullRequest->labels);

            return $context->platform instanceof Gitlab
                && !\in_array('no-trunk', $labels, true)
                && preg_match('#^6\.\d+.*|saas/\d{4}/\d{1,2}$#', $context->platform->raw['target_branch']);
        },
        [
            function (Context $context): void {
                if (str_contains($context->platform->pullRequest->body, '/shopware/6/product/platform/-/merge_requests/')) {
                    return;
                }

                $found = false;

                foreach ($context->platform->pullRequest->getComments() as $comment) {
                    if (str_contains($comment->body, '/shopware/6/product/platform/-/merge_requests/')) {
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    $context->failure('This MR should have a dependency on a trunk MR. Please add a thread with a link');
                }
            }
        ]
    ))
    ->useRule(new Condition(
        function (Context $context) {
            $labels = array_map('strtolower', $context->platform->pullRequest->labels);

            return $context->platform instanceof Gitlab && !\in_array('github', $labels, true);
        },
        [
            function (Context $context): void {
                $files = $context->platform->pullRequest->getFiles();

                /** @var Gitlab $gitlab */
                $gitlab = $context->platform;

                $phpstanBaseline = new GitlabFile(
                    $gitlab->client,
                    $_SERVER['CI_PROJECT_ID'],
                    'phpstan-baseline.neon',
                    $gitlab->raw['sha']
                );

                $fileNames = $files->map(fn (File $f) => $f->name);

                $filesWithIgnoredErrors = [];
                foreach ($fileNames as $fileName) {
                    if (str_contains($phpstanBaseline->getContent(), 'path: ' . $fileName)) {
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
                        . print_r($errorFiles, true)
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
    ->useRule(new Condition(
        function (Context $context) {
            return $context->platform instanceof Gitlab;
        },
        [
            function (Context $context): void {
                $files = $context->platform->pullRequest->getFiles();

                $relevant = $files->matches('src/Core/*.php')->count() > 0
                    || $files->matches('src/Elasticsearch/*.php')->count() > 0
                    || $files->matches('src/Storefront/Migration/')->count() > 0;

                if (!$relevant) {
                    return;
                }

                $labels = ['core__component'];
                if ($files->matches('src/**/Cart/')->count() > 0) {
                    $labels[] = 'core__cart';
                }
                if ($files->matches('src/**/*Definition.php')->count() > 0) {
                    $labels[] = 'core__definition';
                }
                if ($files->matches('src/**/*Route.php')->count() > 0) {
                    $labels[] = 'core__store-api';
                }
                if ($files->matches('src/**/Migration/**/Migration*.php')->count() > 0) {
                    $labels[] = 'core__migration';
                }
                if ($files->matches('src/Elasticsearch/')->count() > 0) {
                    $labels[] = 'core__elasticsearch';
                }
                if ($files->matches('src/**/DataAbstractionLayer/')->count() > 0) {
                    $labels[] = 'core__dal';
                }

                $context->platform->addLabels(...$labels);
            },
        ]
    ))->useRule(new Condition(
        function (Context $context) {
            return $context->platform instanceof Gitlab;
        },
        [
            function (Context $context): void {
                $files = $context->platform->pullRequest->getFiles();

                $bcChange = $files->matches('.bc-exclude.php')->count() > 0;

                if (!$bcChange) {
                    return;
                }

                $context->platform->addLabels('bc_exclude_php');
            },
        ]
    ))
    ->useRule(new Condition(
        function (Context $context) {
            return $context->platform instanceof Gitlab;
        },
        [
            function (Context $context): void {
                if (!preg_match('/(?m)^((WIP:\s)|^(Draft:\s)|^(DRAFT:\s))?(\[[\w.]+]\s)?NEXT-\d*\s-\s\w/', $context->platform->pullRequest->title)) {
                    $context->failure(sprintf('The title `%s` does not match our requirements. Example: NEXT-00000 - My Title', $context->platform->pullRequest->title));
                }
            }
        ]))
    ->useRule(new Condition(
        function (Context $context) {
            return $context->platform instanceof Gitlab;
        },
        [
            function (Context $context): void {
                $labels = $context->platform->pullRequest->labels;

                if (in_array('E2E:skip', $labels, true) || in_array('unit:skip', $labels, true)) {
                    $context->notice('You skipped some tests. Reviewers be carefully with this');
                }
            },
            function (Context $context): void {
                $files = $context->platform->pullRequest->getFiles();
                $hasStoreApiModified = false;

                /** @var File $file */
                foreach ($files->getElements() as $file) {
                    if (str_contains($file->name, 'SalesChannel') && str_contains($file->name, 'Route.php') && !str_contains($file->name, '/Test/')) {
                        $hasStoreApiModified = true;
                    }
                }

                if ($hasStoreApiModified) {
                    $context->warning('Store-API Route has been modified. @Reviewers please review carefully!');
                    $context->platform->addLabels('Security-Audit Required');
                }
            },
        ]
    ))
    ->useRule(function (Context $context): void {
        function checkMigrationForBundle(string $bundle, Context $context): void
        {
            $files = $context->platform->pullRequest->getFiles();

            $migrationFiles = $files->filterStatus(File::STATUS_ADDED)->matches('src/Core/Migration/V*/Migration*.php');
            $migrationTestFiles = $files->filterStatus(File::STATUS_ADDED)->matches('tests/migration/Core/V*/*.php');

            if ($migrationFiles->count() && !$migrationTestFiles->count()) {
                $context->failure('Please add tests for your new Migration file');
            }
        }

        checkMigrationForBundle('Core', $context);
        checkMigrationForBundle('Administration', $context);
        checkMigrationForBundle('Storefront', $context);
    })
    ->useRule(function (Context $context): void {
        $files = $context->platform->pullRequest->getFiles();

        $newSqlHeredocs = $files->filterStatus(File::STATUS_MODIFIED)->matchesContent('/<<<SQL/');

        if ($newSqlHeredocs->count() > 0) {
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
                . print_r($errorFiles, true)
            );
        }
    })
    ->useRule(function (Context $context): void {
        $files = $context->platform->pullRequest->getFiles();

        $changedTemplates = $files->filterStatus(File::STATUS_MODIFIED)->matches('src/Storefront/Resources/views/*.twig')
            ->getElements();

        if (count($changedTemplates) > 0) {
            $patched = [];
            foreach ($changedTemplates as $file) {
                preg_match_all('/\- .*? (\{% block (.*?) %\})+/', $file->patch, $removedBlocks);
                preg_match_all('/\+ .*? (\{% block (.*?) %\})+/', $file->patch, $addedBlocks);
                if (!isset($removedBlocks[2]) || !is_array($removedBlocks[2])) {
                    $removedBlocks[2] = [];
                }
                if (!isset($addedBlocks[2]) || !is_array($addedBlocks[2])) {
                    $addedBlocks[2] = [];
                }

                $remaining = array_diff_assoc($removedBlocks[2], $addedBlocks[2]);

                if (count($remaining) > 0) {
                    $patched[] = print_r($remaining, true) . '<br/>';
                }
            }

            if (count($patched) === 0) {
                return;
            }

            $context->warning(
                'You probably moved or deleted a twig block. This is likely a hard break. Please check your template'
                . ' changes and make sure that deleted blocks are already deprecated. <br/>'
                . 'If you are sure everything is fine with your changes, you can resolve this warning.<br/>'
                . 'Moved or deleted block: <br/>'
                . print_r($patched, true)
            );
        }
    })
    ->useRule(function (Context $context): void {
        $files = $context->platform->pullRequest->getFiles();

        $invalidFiles = [];

        foreach ($files as $file) {
            if (str_starts_with($file->name, '.run/')) {
                continue;
            }

            if ($file->status !== File::STATUS_REMOVED && preg_match('/^([-+\.\w\/]+)$/', $file->name) === 0) {
                $invalidFiles[] = $file->name;
            }
        }

        if (count($invalidFiles) > 0) {
            $context->failure(
                'The following filenames contain invalid special characters, please use only alphanumeric characters, dots, dashes and underscores: <br/>'
                . print_r($invalidFiles, true)
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
                'Don\'t add new testcases in the `/src` folder, for new tests write "real" unit tests under `tests/unit` and if needed a few meaningful integration tests under `tests/integration`: <br/>'
                . print_r($addedLegacyTests, true)
            );
        }
    })
    ->useRule(function (Context $context): void {
        $addedUnitTests = $context->platform->pullRequest->getFiles()->filterStatus(File::STATUS_ADDED)->matches('tests/unit/**/*Test.php');
        $addedSrcFiles = $context->platform->pullRequest->getFiles()->filterStatus(File::STATUS_ADDED)->matches('src/**/*.php');
        $missingUnitTests = [];
        $unitTestsName = [];

        // prepare phpunit code coverage exclude lists
        $phpUnitConfig = __DIR__ . '/phpunit.xml.dist';
        $excludedDirs = [];
        $excludedFiles = [];
        $dom = new \DOMDocument();
        $loaded = $dom->load($phpUnitConfig);
        if ($loaded) {
            $xpath = new \DOMXPath($dom);
            $dirsDomElements = $xpath->query('//source/exclude/directory');

            foreach ($dirsDomElements as $dirDomElement) {
                $excludedDirs[] = [
                    'path'=> rtrim($dirDomElement->nodeValue, '/') . '/',
                    'suffix' => $dirDomElement->getAttribute('suffix') ?: '',
                ];
            }

            $filesDomElements = $xpath->query('//source/exclude/file');

            foreach ($filesDomElements as $fileDomElements) {
                $excludedFiles[] = $fileDomElements->nodeValue;
            }
        } else {
            $context->warning(sprintf('Was not able to load phpunit config file %s. Please check configuration.', $phpUnitConfig));
        }

        foreach ($addedUnitTests as $file) {
            $content = $file->getContent();

            preg_match('/\s+extends\s+(?<class>\w+)/', $content, $matches);

            if (isset($matches['class']) && in_array($matches['class'], BaseTestClasses)) {
                $fqcn = str_replace('.php', '', $file->name);
                $className = explode('/', $fqcn);
                $testClass = end($className);

                $unitTestsName[] = $testClass;
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
                'Please be kind and add unit tests for your new code in these files: <br/>'
                . implode('<br/>', $missingUnitTests)
                . '<br/>' . 'If you are sure everything is fine with your changes, you can resolve this warning. <br /> You can run `composer make:coverage` to generate dummy unit tests for files that are not covered'
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
            if ($composerFile->status === File::STATUS_REMOVED || str_contains((string)$composerFile->name, 'src/WebInstaller') || str_contains((string)$composerFile->name, 'src/Core/DevOps/StaticAnalyze/PHPStan')) {
                continue;
            }

            $composerContent = json_decode($composerFile->getContent(), true);
            /** @var array<string, string> $requirements */
            $requirements = array_merge(
                $composerContent['require'] ?? [],
                $composerContent['require-dev'] ?? []
            );

            foreach ($requirements as $package => $constraint) {
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
