<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\SchemaGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @package core
 */
#[AsCommand(
    name: 'dal:create:schema',
    description: 'Creates the database schema',
)]
class CreateSchemaCommand extends Command
{
    /**
     * @var SchemaGenerator
     */
    private $schemaGenerator;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $registry;

    /**
     * @var string
     */
    private $dir;

    /**
     * @internal
     */
    public function __construct(
        SchemaGenerator $generator,
        DefinitionInstanceRegistry $registry,
        string $rootDir
    ) {
        parent::__construct();
        $this->schemaGenerator = $generator;
        $this->registry = $registry;
        $this->dir = $rootDir . '/schema/';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);
        $io->title('DAL generate schema');

        $entities = $this->registry->getDefinitions();
        $schema = [];

        foreach ($entities as $entity) {
            $domain = explode('_', $entity->getEntityName());
            $domain = array_shift($domain);
            $schema[$domain][] = $this->schemaGenerator->generate($entity);
        }

        $io->success('Created schema in ' . $this->dir);

        if (!file_exists($this->dir)) {
            mkdir($this->dir);
        }

        foreach ($schema as $domain => $sql) {
            file_put_contents($this->dir . '/' . $domain . '.sql', implode("\n\n", $sql));
        }

        return self::SUCCESS;
    }
}
