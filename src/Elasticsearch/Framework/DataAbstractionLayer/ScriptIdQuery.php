<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\DataAbstractionLayer;

use ONGR\ElasticsearchDSL\BuilderInterface;
use ONGR\ElasticsearchDSL\ParametersTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class ScriptIdQuery implements BuilderInterface
{
    use ParametersTrait;

    private string $id;

    /**
     * @param array<mixed> $parameters
     */
    public function __construct(string $id, array $parameters = [])
    {
        $this->id = $id;
        $this->setParameters($parameters);
    }

    /**
     * @return string
     */
    public function getType()
    {
        return 'script';
    }

    /**
     * {@inheritdoc}
     *
     * @return array<mixed>
     */
    public function toArray()
    {
        $query = ['id' => $this->id];
        $output = $this->processArray($query);

        return [$this->getType() => ['script' => $output]];
    }
}
