[titleEn]: <>(Breaking change - Rename DateField to DateTimeField and add real DateField)

Renaming:
Shopware\Core\Framework\DataAbstractionLayer\Field\DateField => Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField
Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\DateFieldSerializer => Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\DateTimeFieldSerializer 
Shopware\Core\Defaults::STORAGE_DATE_FORMAT => Shopware\Core\Defaults::STORAGE_DATE_TIME_FORMAT

New:

Shopware\Core\Framework\DataAbstractionLayer\Field\DateField which only stores dates (without time)