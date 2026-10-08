<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @codeCoverageIgnore
 */
class SchemaExtensionClass extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(new StringField('extra', 'extra'));
    }

    public function getEntityName(): string
    {
        return 'product';
    }
}
