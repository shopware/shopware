<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Changelog\Processor;

use Shopware\Core\Framework\Changelog\ChangelogDefinition;

class ChangelogGenerator extends ChangelogProcessor
{
    public function generate(ChangelogDefinition $template, string $date, bool $dryRun = false): string
    {
        $target = $this->getTemplateFile($template, $date);
        if ($dryRun) {
            echo $template->toTemplate();
        } else {
            file_put_contents($target, $template->toTemplate());
        }

        return $target;
    }

    private function getTemplateFile(ChangelogDefinition $template, string $date): string
    {
        return sprintf(
            '%s/%s-%s.md',
            $this->getUnreleasedDir(),
            $date,
            str_replace(' ', '-', strtolower($template->getTitle()))
        );
    }
}
