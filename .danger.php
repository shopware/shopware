<?php declare(strict_types=1);

use Danger\Config;
use Danger\Context;
use Danger\Platform\Github\Github;
use Danger\Platform\Gitlab\Gitlab;
use Danger\Rule\CommitRegexRule;
use Danger\Rule\ConditionRule;
use Danger\Rule\DisallowRepeatedCommitsRule;
use Danger\Rule\MaxCommitRule;


return (new Config())
    ->useThreadOnFails()
    ->useGithubCommentProxy('https://clknq19sx1.execute-api.eu-central-1.amazonaws.com')
    ->useRule(new DisallowRepeatedCommitsRule)
    ->useRule(function (Context $context) {
        $files = $context->platform->pullRequest->getFiles();

        if ($files->matches('changelog/_unreleased/*.md')->count() === 0) {
            $context->warning('The Pull Request doesn\'t contain any changelog file');
        }
    })
    ->useRule(function (Context $context) {
        // The title is not important here as we import the pull requests and prefix them
        if ($context->platform->pullRequest->projectIdentifier === 'shopware/platform') {
            return;
        }

        if (!preg_match('/(?m)^(WIP:\s)?NEXT-\d*\s-\s\w/', $context->platform->pullRequest->title)) {
            $context->failure(sprintf('The title `%s` does not match our requirements. Example: NEXT-00000 - My Title', $context->platform->pullRequest->title));
        }
    })
    ->useRule(new ConditionRule(
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

                /** @var Danger\Struct\File $file */
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
    ->useRule(new ConditionRule(
        function (Context $context) {
            return $context->platform instanceof Github;
        },
        [
            new MaxCommitRule()
        ]
    ))
    ->useRule(new ConditionRule(
        function (Context $context) {
            return $context->platform instanceof Github && $context->platform->pullRequest->projectIdentifier === 'shopwareBoostDay/platform';
        },
        [
            new CommitRegexRule(
                '/(?m)(?mi)^NEXT-\d*\s-\s[A-Z].*,\s*fixes\s*shopwareBoostday\/platform#\d*$/m',
                "The commit title `###MESSAGE###` does not match our requirements. Example: \"NEXT-00000 - My Title, fixes shopwareBoostday/platform#1234\""
            )
        ]
    ))
    ->after(function (Context $context) {
        if ($context->platform instanceof Github && $context->hasFailures()) {
            $context->platform->addLabels('Incomplete');
        }
    })
;
