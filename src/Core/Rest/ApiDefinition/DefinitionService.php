<?php declare(strict_types=1);

namespace Shopware\Rest\ApiDefinition;

class DefinitionService
{
    /**
     * @var ApiDefinitionGeneratorInterface[]
     */
    private $generators;

    public function __construct(iterable $generators)
    {
        $this->generators = $generators;
    }

    public function generate($format = 'openapi-3'): array
    {
        return $this->getGenerator($format)->generate();
    }

    public function getSchema($format = 'openapi-3'): array
    {
        return $this->getGenerator($format)->getSchema();
    }

    /**
     * @param string $format
     *
     * @throws ApiDefinitionGeneratorNotFoundException
     *
     * @return ApiDefinitionGeneratorInterface
     */
    private function getGenerator(string $format): ApiDefinitionGeneratorInterface
    {
        foreach ($this->generators as $generator) {
            if ($generator->supports($format)) {
                return $generator;
            }
        }

        throw new ApiDefinitionGeneratorNotFoundException($format);
    }
}
