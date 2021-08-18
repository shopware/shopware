<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Demodata;

use Faker\Factory;
use Faker\Generator;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Demodata\Faker\Commerce;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

class DemodataService
{
    /**
     * @var DemodataGeneratorInterface[]
     */
    private $generators;

    /**
     * @var string
     */
    private $projectDir;

    /**
     * @var DefinitionInstanceRegistry
     */
    private $registry;

    /**
     * @param \IteratorAggregate<DemodataGeneratorInterface> $generators
     */
    public function __construct(
        \IteratorAggregate $generators,
        string $projectDir,
        DefinitionInstanceRegistry $registry
    ) {
        $this->projectDir = $projectDir;
        $this->generators = iterator_to_array($generators);
        $this->registry = $registry;
    }

    public function generate(DemodataRequest $request, Context $context, ?SymfonyStyle $console): DemodataContext
    {
        if (!$console) {
            $console = new ShopwareStyle(new ArgvInput(), new NullOutput());
        }

        $faker = $this->getFaker();

        $demodataContext = new DemodataContext($context, $faker, $this->projectDir, $console, $this->registry);

        foreach ($request->all() as $definitionClass => $numberOfItems) {
            if ($numberOfItems === 0) {
                continue;
            }

            $definition = $this->registry->get($definitionClass);

            $console->section(sprintf('Generating %d items for %s', $numberOfItems, $definition->getEntityName()));

            $validGenerators = array_filter($this->generators, static function (DemodataGeneratorInterface $generator) use ($definitionClass) {
                return $generator->getDefinition() === $definitionClass;
            });

            if (empty($validGenerators)) {
                throw new \RuntimeException(
                    sprintf('Could not generate demodata for "%s" because no generator is registered.', $definitionClass)
                );
            }

            $start = microtime(true);

            foreach ($validGenerators as $generator) {
                $generator->generate($numberOfItems, $demodataContext, $request->getOptions($definitionClass));
            }

            $end = microtime(true) - $start;

            $console->note(sprintf('Took %f seconds', $end));

            $demodataContext->setTiming($definition, $numberOfItems, $end);
        }

        return $demodataContext;
    }

    private function getFaker(): Generator
    {
        $faker = Factory::create('de-DE');
        $faker->addProvider(new Commerce($faker));

        /*
         * @deprecated tag:v6.5.0 remove and replace by importing \Maltyxx\ImagesGenerator\ImagesGeneratorProvider
         */
        if (\class_exists(\Maltyxx\ImagesGenerator\ImagesGeneratorProvider::class)) {
            $faker->addProvider(new \Maltyxx\ImagesGenerator\ImagesGeneratorProvider($faker));
        } else {
            $faker->addProvider(new \bheller\ImagesGenerator\ImagesGeneratorProvider($faker));
        }

        return $faker;
    }
}
