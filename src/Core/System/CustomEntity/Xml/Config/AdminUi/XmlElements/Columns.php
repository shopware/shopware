<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomEntity\Xml\Config\ConfigXmlElement;

/**
 * Represents the XML columns element
 *
 * admin-ui > entity > listing > columns
 *
 * @internal
 */
#[Package('content')]
final class Columns extends ConfigXmlElement
{
    /**
     * @var list<Column>
     */
    protected array $content;

    /**
     * @return list<Column>
     */
    public function getContent(): array
    {
        return $this->content;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        return $data['content'];
    }

    protected static function parse(\DOMElement $element): array
    {
        $columns = [];
        foreach ($element->getElementsByTagName('column') as $column) {
            $columns[] = Column::fromXml($column);
        }

        return ['content' => $columns];
    }
}
