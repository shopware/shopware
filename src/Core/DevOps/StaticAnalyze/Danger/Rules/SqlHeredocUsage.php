<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use Shopware\Core\Framework\Log\Package;

/**
 * SQL strings belong in Nowdoc (`<<<'SQL'`) instead of Heredoc (`<<<SQL`), so accidental
 * variable interpolation into SQL is impossible.
 *
 * @internal
 */
#[Package('framework')]
class SqlHeredocUsage
{
    public function __invoke(Context $context): void
    {
        // match against the patch instead of matchesContent(): the diff ships with the file listing,
        // while matchesContent() downloads the full body of every modified file via the API
        $newSqlHeredocs = $context->platform->pullRequest->getFiles()
            ->filterStatus(File::STATUS_MODIFIED)
            ->filter(static fn (File $file): bool => preg_match('/^\+.*<<<SQL/m', $file->patch) === 1);

        if ($newSqlHeredocs->count() <= 0) {
            return;
        }

        $errorFiles = [];
        foreach ($newSqlHeredocs as $file) {
            if ($file->name !== '.danger.php') {
                $errorFiles[] = $file->name . '<br/>';
            }
        }

        if ($errorFiles === []) {
            return;
        }

        $context->failure(
            'Please use [Nowdoc](https://www.php.net/manual/de/language.types.string.php#language.types.string.syntax.nowdoc)'
            . ' for SQL (&lt;&lt;&lt;\'SQL\') instead of Heredoc (&lt;&lt;&lt;SQL)<br/>'
            . implode('<br>', $errorFiles)
        );
    }
}
