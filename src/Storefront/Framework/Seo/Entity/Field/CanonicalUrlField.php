<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\Entity\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;

class CanonicalUrlField extends BlobField
{
    /**
     * @var string
     */
    private $routeName;

    public function __construct(string $propertyName, string $routeName)
    {
        parent::__construct($propertyName, $propertyName);
        $this->addFlags(new WriteProtected(), new Extension(), new Runtime());
        $this->routeName = $routeName;
    }

    public function getRouteName(): string
    {
        return $this->routeName;
    }
}
