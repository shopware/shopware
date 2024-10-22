const uniqueSlotsKebab = [
    'buy-box',
    'product-description-reviews',
    'cross-selling',
];

/**
 * @package buyers-experience
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Object.freeze({
    REQUIRED_FIELD_ERROR_CODE: 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
    PAGE_TYPES: {
        SHOP: 'page',
        LANDING: 'landingpage',
        LISTING: 'product_list',
        PRODUCT_DETAIL: 'product_detail',
    },
    TYPE_MAPPING_ENTITIES: {
        product_detail: {
            entity: 'product',
            mode: 'single',
        },
        product_list: {
            entity: 'category',
            mode: 'single',
        },
    },
    UNIQUE_SLOTS: uniqueSlotsKebab.map((slotName) => slotName.replace(/-./g, (char) => char.toUpperCase()[1])),
    UNIQUE_SLOTS_KEBAB: uniqueSlotsKebab,
    SLOT_POSITIONS: {
        left: 0,
        'left-image': 100,
        'left-top': 200,
        'left-text': 300,
        'left-bottom': 400,
        'center-left': 1000,
        center: 1100,
        'center-image': 1200,
        'center-top': 1300,
        'center-text': 1400,
        'center-bottom': 1500,
        'center-right': 1600,
        right: 2000,
        'right-image': 2100,
        'right-top': 2200,
        'right-text': 2300,
        'right-bottom': 2400,
        content: 3000,
        image: 3100,
        video: 3200,
        imageSlider: 3300,
        default: 5000,
    },
    MEDIA: {
        previewCamera: 'bundles/administration/static/img/cms/preview_camera_large.jpg',
        previewMountain: 'bundles/administration/static/img/cms/preview_mountain_large.jpg',
        previewPlant: 'bundles/administration/static/img/cms/preview_plant_large.jpg',
        previewGlasses: 'bundles/administration/static/img/cms/preview_glasses_large.jpg',
    },
});
