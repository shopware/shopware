<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\DataResolver;

use Shopware\Core\Framework\Struct\Struct;

class FieldConfig extends Struct
{
    public const SOURCE_STATIC = 'static';
    public const SOURCE_MAPPED = 'mapped';
    public const SOURCE_PRODUCT_STREAM = 'product_stream';

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $source;

    protected $value;

    public function __construct(string $name, string $source, $value)
    {
        $this->name = $name;
        $this->source = $source;
        $this->value = $value;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function isStatic(): bool
    {
        return $this->source === self::SOURCE_STATIC;
    }

    public function isMapped(): bool
    {
        return $this->source === self::SOURCE_MAPPED;
    }

    public function isProductStream(): bool
    {
        return $this->source === self::SOURCE_PRODUCT_STREAM;
    }

    public function getApiAlias(): string
    {
        return 'cms_data_resolver_field_config';
    }
}
