<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\DataAbstractionLayer;

use ONGR\ElasticsearchDSL\BuilderInterface;
use ONGR\ElasticsearchDSL\ParametersTrait;

class ScriptIdQuery implements BuilderInterface
{
    use ParametersTrait;

    /**
     * @var string
     */
    private $id;

    /**
     * @param string $id         Script
     * @param array  $parameters Optional parameters
     */
    public function __construct($id, array $parameters = [])
    {
        $this->id = $id;
        $this->setParameters($parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function getType()
    {
        return 'script';
    }

    /**
     * {@inheritdoc}
     */
    public function toArray()
    {
        $query = ['id' => $this->id];
        $output = $this->processArray($query);

        return [$this->getType() => ['script' => $output]];
    }
}
