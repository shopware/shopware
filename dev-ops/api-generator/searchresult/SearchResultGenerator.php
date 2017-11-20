<?php

class SearchResultGenerator
{
    protected $directory;

    public function __construct($directory)
    {
        $this->directory = $directory;
    }

    public function generate(array $definitions, Context $context)
    {
        /** @var TableDefinition $definition */
        foreach ($definitions as $definition) {
            if ($definition->isMappingTable) {
                continue;
            }

            $this->generateSearchResultClass($definition, $context);
        }

    }

    private function generateSearchResultClass(TableDefinition $definition, Context $context)
    {
        $template = file_get_contents(__DIR__ . '/template.txt');

        $template = str_replace(
            ['#bundle#', '#classUc#'],
            [
                ucfirst($definition->bundle),
                ucfirst($definition->domainName),
            ],
            $template
        );

        $file = sprintf(
            $this->directory.'/%s/Struct/%sSearchResult.php',
            ucfirst($definition->bundle),
            ucfirst($definition->domainName)
        );

        file_put_contents($file, $template);
    }
}