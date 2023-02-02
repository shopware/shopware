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
                        'Some files you touched in your MR contain ignored phpstan errors. Please be nice and fix all ignored errors for the following files:<br>'
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
                if ($files->matches('src/Storefront/Migration/')->count() > 0) {
                    $labels[] = 'core__migration';
                }
                if ($files->matches('src/Core/Migration/')->count() > 0) {
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
    ->useRule(function (Context $context): void {
        // The title is not important here as we import the pull requests and prefix them
        if ($context->platform->pullRequest->projectIdentifier === 'shopware/platform') {
            return;
        }

        if (!preg_match('/(?m)^((WIP:\s)|^(Draft:\s)|^(DRAFT:\s))?NEXT-\d*\s-\s\w/', $context->platform->pullRequest->title)) {
            $context->failure(sprintf('The title `%s` does not match our requirements. Example: NEXT-00000 - My Title', $context->platform->pullRequest->title));
        }
    })
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
    ->useRule(new Condition(
        function (Context $context) {
            return $context->platform instanceof Github && $context->platform->pullRequest->projectIdentifier === 'shopwareBoostDay/platform';
        },
        [
            new CommitRegex(
                '/(?m)(?mi)^NEXT-\d*\s-\s[A-Z].*,\s*fixes\s*shopwareBoostday\/platform#\d*$/m',
                'The commit title `###MESSAGE###` does not match our requirements. Example: "NEXT-00000 - My Title, fixes shopwareBoostday/platform#1234"'
            ),
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
    ->after(function (Context $context): void {
        if ($context->platform instanceof Github && $context->hasFailures()) {
            $context->platform->addLabels('Incomplete');
        }
    })
;
