<?php declare(strict_types=1);

use Danger\Config;
use Danger\Context;
use Danger\Struct\File;
use Danger\Platform\Github\Github;
use Danger\Platform\Gitlab\Gitlab;
use Danger\Rule\CommitRegex;
use Danger\Rule\Condition;
use Danger\Rule\DisallowRepeatedCommits;


return (new Config())
    ->useThreadOnFails()
    ->useRule(new DisallowRepeatedCommits)
    ->useRule(function (Context $context) {
        $files = $context->platform->pullRequest->getFiles();

        if ($files->matches('changelog/_unreleased/*.md')->count() === 0) {
            $context->warning('The Pull Request doesn\'t contain any changelog file');
        }
    })
    ->useRule(new Condition(
        function(Context $context) {
            return $context->platform instanceof Gitlab;
        },
        [
            function(Context $context) {
                $files = $context->platform->pullRequest->getFiles();

                $relevant = (
                    $files->matches('src/Core/*.php')->count() > 0
                    ||
                    $files->matches('src/Elasticsearch/*.php')->count() > 0
                    ||
                    $files->matches('src/Storefront/Migration/')->count() > 0
                );

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
            }
        ]
    ))
    ->useRule(function (Context $context) {
        // The title is not important here as we import the pull requests and prefix them
        if ($context->platform->pullRequest->projectIdentifier === 'shopware/platform') {
            return;
        }

        if (!preg_match('/(?m)^(WIP:\s)?NEXT-\d*\s-\s\w/', $context->platform->pullRequest->title)) {
            $context->failure(sprintf('The title `%s` does not match our requirements. Example: NEXT-00000 - My Title', $context->platform->pullRequest->title));
        }
    })
    ->useRule(new Condition(
        function (Context $context) {
            return $context->platform instanceof Gitlab;
        },
        [
            function (Context $context) {
                $labels = $context->platform->pullRequest->labels;

                if (in_array('E2E:skip', $labels, true) || in_array('unit:skip', $labels, true)) {
                    $context->notice('You skipped some tests. Reviewers be carefully with this');
                }
            },
            function (Context $context) {
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
            }
        ]
    ))
    ->useRule(new Condition(
        function (Context $context) {
            return $context->platform instanceof Github && $context->platform->pullRequest->projectIdentifier === 'shopwareBoostDay/platform';
        },
        [
            new CommitRegex(
                '/(?m)(?mi)^NEXT-\d*\s-\s[A-Z].*,\s*fixes\s*shopwareBoostday\/platform#\d*$/m',
                "The commit title `###MESSAGE###` does not match our requirements. Example: \"NEXT-00000 - My Title, fixes shopwareBoostday/platform#1234\""
            )
        ]
    ))
    ->useRule(function (Context $context) {
        $files = $context->platform->pullRequest->getFiles();

        $migrationFiles = $files->filterStatus(File::STATUS_ADDED)->matches('src/Core/Migration/V*/Migration*.php');
        $migrationTestFiles = $files->filterStatus(File::STATUS_ADDED)->matches('src/Core/Migration/Test/*.php');

        if ($migrationFiles->count() && !$migrationTestFiles->count()) {
            $context->failure('Please add tests for your new Migration file');
        }
    })
    ->useRule(function (Context $context) {
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
                'Please use [Nowdoc](https://www.php.net/manual/de/language.types.string.php#language.types.string.syntax.nowdoc)' .
                ' for SQL (&lt;&lt;&lt;\'SQL\') instead of Heredoc (&lt;&lt;&lt;SQL)<br/>' .
                print_r($errorFiles, true)
            );
        }
    })
    ->after(function (Context $context) {
        if ($context->platform instanceof Github && $context->hasFailures()) {
            $context->platform->addLabels('Incomplete');
        }
    })
;
