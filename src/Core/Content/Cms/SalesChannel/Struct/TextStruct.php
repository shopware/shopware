<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\SalesChannel\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('discovery')]
class TextStruct extends Struct
{
    protected ?string $content = null;

    /**
     * @var array<string, mixed>
     */
    protected array $contentSchema = [];

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    /**
    * @return array<string, mixed>
    */
    public function getContentSchema(): array
    {
        return $this->contentSchema;
    }

    /**
     * @param array<string, mixed> $contentSchema
     */
    public function setContentSchema(array $contentSchema): void
    {
        $this->contentSchema = $contentSchema;
    }

    public function getApiAlias(): string
    {
        return 'cms_text';
    }
}
