<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata;

use bheller\ImagesGenerator\ImagesGeneratorProvider;
use Doctrine\DBAL\Connection;
use Faker\Factory;
use Faker\Generator;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Demodata\Faker\Commerce;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

class DemodataService
{
    /**
     * @var DemodataGeneratorInterface[]
     */
    private $generators = [];

    /**
     * @var string
     */
    private $projectDir;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $registry;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @param DemodataGeneratorInterface[] $generators
     */
    public function __construct(
        Connection $connection,
        iterable $generators,
        string $projectDir,
        DefinitionInstanceRegistry $registry
    ) {
        $this->projectDir = $projectDir;

        foreach ($generators as $generator) {
            $this->generators[$generator->getDefinition()] = $generator;
        }
        $this->registry = $registry;
        $this->connection = $connection;
    }

    public function generate(DemodataRequest $request, Context $context, ?SymfonyStyle $console): DemodataContext
    {
        if (!$console) {
            $console = new ShopwareStyle(new ArgvInput(), new NullOutput());
        }

        $faker = $this->getFaker();

        $demodataContext = new DemodataContext($this->connection, $context, $faker, $this->projectDir, $console, $this->registry);

        /** @var EntityDefinition|string $definitionClass */
        foreach ($request->all() as $definitionClass => $numberOfItems) {
            if ($numberOfItems === 0) {
                continue;
            }

            $definition = $this->registry->get($definitionClass);

            $console->section(sprintf('Generating %d items for %s', $numberOfItems, $definition->getEntityName()));

            $generator = $this->generators[$definitionClass] ?? null;

            if (!$generator) {
                throw new \RuntimeException(sprintf('Could not generate demodata for "%s" because no generator is registered.', $definitionClass));
            }

            $start = microtime(true);
            $generator->generate($numberOfItems, $demodataContext, $request->getOptions($definitionClass));
            $end = microtime(true) - $start;

            $console->note(sprintf('Took %f seconds', (float) $end));

            $demodataContext->setTiming($definition, $numberOfItems, $end);
        }

        return $demodataContext;
    }

    private function getFaker(): Generator
    {
        $faker = Factory::create('de-DE');
        $faker->addProvider(new Commerce($faker));
        $faker->addProvider(new ImagesGeneratorProvider($faker));

        return $faker;
    }
}
