<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Changelog;

class ChangelogGenerator
{
    use ChangelogReleaseTrait;

    public function __construct(string $projectDir)
    {
        $this->initialize($projectDir);
    }

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
            $this->unreleasedDir,
            $date,
            str_replace(' ', '-', strtolower($template->getTitle()))
        );
    }
}
