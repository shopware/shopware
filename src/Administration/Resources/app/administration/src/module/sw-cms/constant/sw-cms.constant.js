const uniqueSlotsKebab = [
    'buy-box',
    'product-description-reviews',
    'cross-selling',
];

export default Object.freeze({
    PAGE_TYPES: {
        SHOP: 'page',
        LANDING: 'landingpage',
        LISTING: 'product_list',
        PRODUCT_DETAIL: 'product_detail',
    },
    UNIQUE_SLOTS: uniqueSlotsKebab
        .map((slotName) => slotName.replace(/-./g, char => char.toUpperCase()[1])),
    UNIQUE_SLOTS_KEBAB: uniqueSlotsKebab,
});
