<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\Entity\Serializer;

use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\OneToManyAssociationFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteCommandExtractor;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Storefront\Framework\Seo\Entity\Field\SeoUrlAssociationField;
use Shopware\Storefront\Framework\Seo\SeoServiceInterface;
use Shopware\Storefront\Framework\Seo\SeoUrl\SeoUrlEntity;

class SeoUrlFieldSerializer extends OneToManyAssociationFieldSerializer
{
    /**
     * @var SeoServiceInterface
     */
    private $seoService;

    public function __construct(WriteCommandExtractor $writeExtractor, SeoServiceInterface $seoService)
    {
        parent::__construct($writeExtractor);
        $this->seoService = $seoService;
    }

    public function getFieldClass(): string
    {
        return SeoUrlAssociationField::class;
    }

    public function encode(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): \Generator
    {
        if (!$field instanceof SeoUrlAssociationField) {
            throw new InvalidSerializerFieldException(SeoUrlAssociationField::class, $field);
        }

        $entityId = Uuid::fromBytesToHex($existence->getPrimaryKey()[$field->getLocalField()]);

        $seoUrls = $data->getValue();
        foreach ($seoUrls as $i => $seoUrl) {
            $seoUrl['routeName'] = $field->getRouteName();
            $seoUrl['isModified'] = true;

            if (!isset($seoUrl['pathInfo']) && $existence->exists()) {
                /** @var SeoUrlEntity|null $generated */
                $generated = @current($this->seoService->generateSeoUrls($seoUrl['salesChannelId'], $field->getRouteName(), [$entityId]));

                if ($generated) {
                    $seoUrl['pathInfo'] = $generated->getPathInfo();
                }
            }

            $seoUrls[$i] = $seoUrl;
        }

        $data = new KeyValuePair($data->getKey(), $seoUrls, $data->isRaw());

        return parent::encode($field, $existence, $data, $parameters);
    }
}
