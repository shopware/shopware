<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use Shopware\Core\Framework\Log\Package;

/**
 * File names are restricted to alphanumeric characters, dots, dashes and underscores so they
 * stay safe across filesystems, packaging and shell handling.
 *
 * @internal
 */
#[Package('framework')]
class InvalidFileNameCharacters
{
    public function __invoke(Context $context): void
    {
        $invalidFiles = [];

        foreach ($context->platform->pullRequest->getFiles() as $file) {
            if (str_starts_with($file->name, '.run/')) {
                continue;
            }

            if ($file->status !== File::STATUS_REMOVED && preg_match('/^([-+\.\w\/]+)$/', $file->name) === 0) {
                $invalidFiles[] = $file->name;
            }
        }

        if ($invalidFiles !== []) {
            $context->failure(
                'The following filenames contain invalid special characters, please use only alphanumeric characters, dots, dashes and underscores:<br/>'
                . implode('<br>', $invalidFiles)
            );
        }
    }
}
