/* istanbul ignore file */
/**
 * @package system-settings
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export const productProfileOnlyRequired = [
    {
        id: 'a98fd824b06c4fa7b17f01e11ccdcc07',
        key: 'translations.DEFAULT.createdAt',
        mappedKey: 'translations_default_created_at',
    }, {
        id: 'ae6175b7097e4a6986483e5e707f548f',
        key: 'translations.DEFAULT.name',
        mappedKey: 'translations_default_name',
    }, {
        id: 'b36961c5f32c4f4d9e17ed9718f5fca2',
        key: 'productNumber',
        mappedKey: 'product_number',
    }, {
        id: '377feb544ed74e8aa97853d6039b8bbd',
        key: 'price.DEFAULT.gross',
        mappedKey: 'price_default_gross',
    }, {
        id: 'fc416f509b0b46fabb8cd8728cf63531',
        key: 'taxId',
        mappedKey: 'tax_id',
    }, {
        id: 'ecb3632b82994453aa48b6a666e33fea',
        key: 'productManufacturerVersionId',
        mappedKey: 'product_manufacturer_version_id',
    }, {
        id: 'fa08a65cfdcf4021b4421c6f4bff4cbf',
        key: 'stock',
        mappedKey: 'stock',
    }, {
        id: 'aa30f4742b4d42beb41b6bf27d0742a2',
        key: 'parentVersionId',
        mappedKey: 'parent_version_id',
    }, {
        id: '090b44140ca44e92adec1bb135c1b8a9',
        key: 'versionId',
        mappedKey: 'version_id',
    }, {
        id: '63fdbdae6cf64b90849b3e0a04677e25',
        key: 'id',
        mappedKey: 'id',
    }, {
        id: '63fdbdae6cf64b90849b3e0a04677e25',
        key: 'cmsPageVersionId',
        mappedKey: 'cms_page-version_id',
    }, {
        id: 'ecb3632b82994453aa48b6a666e33fea',
        key: 'productMediaVersionId',
        mappedKey: 'product_media_version_id',
    },
];

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export const productDuplicateProfileOnlyRequired = [
    {
        id: 'b36961c5f32c4f4d9e17ed9718f5fca2',
        key: 'productNumber',
        mappedKey: 'product_number',
    }, {
        id: 'fc416f509b0b46fabb8cd8728cf63531',
        key: 'taxId',
        mappedKey: 'tax_id',
    }, {
        id: '63fdbdae6cf64b90849b3e0a04677e25',
        key: 'id',
        mappedKey: 'id',
    },
];

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export const mediaProfileOnlyRequired = [
    {
        id: '63fdbdae6cf64b90849b3e0a04677e25',
        key: 'id',
        mappedKey: 'id',
    },
    {
        id: 'a98fd824b06c4fa7b17f01e11ccdcc07',
        key: 'translations.DEFAULT.createdAt',
        mappedKey: 'translations_default_created_at',
    },
];

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    productProfileOnlyRequired,
    mediaProfileOnlyRequired,
};
