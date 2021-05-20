<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Command;

use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateEntitiesCommand extends Command
{
    protected static $defaultName = 'dal:create:entities';

    /**
     * @var EntityGenerator
     */
    private $entityGenerator;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $registry;

    /**
     * @var string
     */
    private $dir;

    public function __construct(
        EntityGenerator $entityGenerator,
        DefinitionInstanceRegistry $registry,
        string $rootDir
    ) {
        parent::__construct();
        $this->registry = $registry;
        $this->dir = $rootDir . '/../schema/';
        $this->entityGenerator = $entityGenerator;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);
        $io->title('DAL generate schema');

        if (!file_exists($this->dir)) {
            mkdir($this->dir);
        }

        $entities = $this->registry->getDefinitions();
        $classes = [];

        foreach ($entities as $entity) {
            $domain = explode('_', $entity->getEntityName());
            $domain = array_shift($domain);
            $classes[$domain][] = $this->entityGenerator->generate($entity);
        }

        $io->success('Created schema in ' . $this->dir);

        foreach ($classes as $domain => $domainClasses) {
            foreach ($domainClasses as $entityClasses) {
                if (empty($entityClasses)) {
                    continue;
                }

                if (!file_exists($this->dir . '/' . $domain)) {
                    mkdir($this->dir . '/' . $domain);
                }

                foreach ($entityClasses as $file => $content) {
                    file_put_contents($this->dir . '/' . $domain . '/' . $file, $content);
                }
            }
        }

        return self::SUCCESS;
    }
}
