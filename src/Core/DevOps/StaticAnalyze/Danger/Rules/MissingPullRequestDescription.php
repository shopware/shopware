<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * Fails pull requests whose description is empty or too short to explain the change.
 *
 * Only the text the author wrote counts: HTML comments and every line the pull request
 * template already ships (headings, checklist, hints) are boilerplate and are ignored, so an
 * untouched template is treated the same as an empty description.
 *
 * @internal
 */
#[Package('framework')]
class MissingPullRequestDescription
{
    public const MIN_LENGTH = 50;

    public function __construct(private readonly string $templatePath = __DIR__ . '/../../../../../../.github/PULL_REQUEST_TEMPLATE.md')
    {
    }

    public function __invoke(Context $context): void
    {
        $description = $this->authoredText($context->platform->pullRequest->body);

        if ($description === '') {
            $context->failure(\sprintf(
                'The pull request has no description. Please fill in the pull request template: why the change is necessary and what it does exactly (at least %d characters).',
                self::MIN_LENGTH
            ));

            return;
        }

        if (mb_strlen($description) < self::MIN_LENGTH) {
            $context->failure(\sprintf(
                'The pull request description is too short (%d of at least %d characters). Please explain why the change is necessary and what it does exactly.',
                mb_strlen($description),
                self::MIN_LENGTH
            ));
        }
    }

    /**
     * The description without HTML comments and without the lines the template ships verbatim.
     */
    private function authoredText(string $body): string
    {
        $body = (string) preg_replace('/<!--.*?-->/s', '', $body);

        $templateLines = array_flip($this->templateLines());
        $authored = [];

        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            // a ticked checklist item is still the template's line, not authored text
            $line = (string) preg_replace('/^- \[[xX]\]/', '- [ ]', trim($line));

            if ($line === '' || isset($templateLines[$line])) {
                continue;
            }

            $authored[] = $line;
        }

        return implode(' ', $authored);
    }

    /**
     * @return list<string>
     */
    private function templateLines(): array
    {
        if (!is_file($this->templatePath)) {
            return [];
        }

        $template = (string) preg_replace('/<!--.*?-->/s', '', (string) file_get_contents($this->templatePath));

        return array_values(array_filter(array_map('trim', preg_split('/\R/', $template) ?: [])));
    }
}
