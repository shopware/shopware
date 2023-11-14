<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\DataAbstractionLayer;

use OpenSearchDSL\BuilderInterface;
use OpenSearchDSL\ParametersTrait;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.6.0 - Will be removed, use OpenSearchDSL\Sort\ScriptSort instead
 */
#[Package('core')]
class ScriptIdQuery implements BuilderInterface
{
    use ParametersTrait;

    private ?string $id;

    private ?string $source;

    /**
     * @param array<mixed> $parameters
     */
    public function __construct(
        ?string $id = null,
        array $parameters = [],
        ?string $source = null
    ) {
        $this->id = $id;
        $this->source = $source;
        $this->setParameters($parameters);
    }

    public function getType(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6_6_0_0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, '6.6.0.0')
        );

        return 'script';
    }

    /**
     * {@inheritdoc}
     *
     * @return array<mixed>
     */
    public function toArray(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6_6_0_0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, '6.6.0.0')
        );

        $query = [];

        if ($this->id) {
            $query['id'] = $this->id;
        }

        if ($this->source) {
            $query['source'] = $this->source;
            $query['lang'] = 'painless';
        }

        $output = $this->processArray($query);

        return [$this->getType() => ['script' => $output]];
    }
}
