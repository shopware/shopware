<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use Shopware\Core\Framework\Log\Package;

/**
 * Twig blocks are a public theme extension point: moving or deleting one is a hard break and
 * must go through a deprecation first.
 *
 * @internal
 */
#[Package('framework')]
class RemovedTwigBlocks
{
    public function __invoke(Context $context): void
    {
        $changedTemplates = $context->platform->pullRequest->getFiles()
            ->filterStatus(File::STATUS_MODIFIED)
            ->matches('src/Storefront/Resources/views/*.twig')
            ->getElements();

        if (\count($changedTemplates) <= 0) {
            return;
        }

        $patched = [];
        foreach ($changedTemplates as $file) {
            preg_match_all('/-.*?(\{% block (.*?) %})+/', $file->patch, $removedBlocks);
            preg_match_all('/\+.*?(\{% block (.*?) %})+/', $file->patch, $addedBlocks);

            $remaining = array_diff_assoc($removedBlocks[2], $addedBlocks[2]);

            foreach ($remaining as $item) {
                $patched[] = $item;
            }
        }

        if ($patched === []) {
            return;
        }

        $context->warning(
            'You probably moved or deleted a twig block. This is likely a hard break. Please check your template'
            . ' changes and make sure that deleted blocks are already deprecated.<br/>'
            . 'If you are sure everything is fine with your changes, you can resolve this warning.<br/>'
            . 'Moved or deleted block:<br/>'
            . implode('<br>', $patched)
        );
    }
}
