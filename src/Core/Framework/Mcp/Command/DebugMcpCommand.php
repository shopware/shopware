<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Command;

use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Capability\Attribute\McpResource;
use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[AsCommand(name: 'debug:mcp', description: 'List registered MCP capabilities (tools, prompts, resources)')]
#[Package('framework')]
class DebugMcpCommand extends Command
{
    /**
     * @internal
     *
     * @param iterable<object> $tools
     * @param iterable<object> $prompts
     * @param iterable<object> $resources
     */
    public function __construct(
        private readonly iterable $tools,
        private readonly iterable $prompts,
        private readonly iterable $resources,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->renderSection($io, $output, 'Tools', $this->tools);
        $this->renderSection($io, $output, 'Prompts', $this->prompts);
        $this->renderSection($io, $output, 'Resources', $this->resources);

        return self::SUCCESS;
    }

    /**
     * @param iterable<object> $items
     */
    private function renderSection(SymfonyStyle $io, OutputInterface $output, string $title, iterable $items): void
    {
        $io->section($title);

        $rows = [];
        foreach ($items as $item) {
            $ref = new \ReflectionClass($item);
            $attrs = $this->extractMcpAttributes($ref);

            if ($attrs === []) {
                $rows[] = [$ref->getShortName(), '(no MCP attribute found)', ''];
                continue;
            }

            foreach ($attrs as $attr) {
                $rows[] = [
                    $attr['name'],
                    $attr['description'] ?? '',
                    $attr['class'],
                ];
            }
        }

        if ($rows === []) {
            $io->text('No capabilities registered.');

            return;
        }

        $table = new Table($output);
        $table->setHeaders(['Name', 'Description', 'Class']);
        $table->setRows($rows);
        $table->render();

        $io->newLine();
    }

    /**
     * @param \ReflectionClass<object> $ref
     *
     * @return list<array{name: string, description: ?string, class: string}>
     */
    private function extractMcpAttributes(\ReflectionClass $ref): array
    {
        $result = [];

        $mcpAttributeClasses = [
            McpTool::class,
            McpPrompt::class,
            McpResource::class,
        ];

        foreach ($ref->getAttributes() as $attr) {
            if (\in_array($attr->getName(), $mcpAttributeClasses, true)) {
                /** @var McpTool|McpPrompt|McpResource $instance */
                $instance = $attr->newInstance();
                $result[] = [
                    'name' => $instance->name ?? $ref->getShortName(),
                    'description' => $instance->description ?? null,
                    'class' => $ref->getName(),
                ];
            }
        }

        foreach ($ref->getMethods() as $method) {
            foreach ($method->getAttributes() as $attr) {
                if (\in_array($attr->getName(), $mcpAttributeClasses, true)) {
                    /** @var McpTool|McpPrompt|McpResource $instance */
                    $instance = $attr->newInstance();
                    $result[] = [
                        'name' => $instance->name ?? $method->getName(),
                        'description' => $instance->description ?? null,
                        'class' => $ref->getName() . '::' . $method->getName(),
                    ];
                }
            }
        }

        return $result;
    }
}
