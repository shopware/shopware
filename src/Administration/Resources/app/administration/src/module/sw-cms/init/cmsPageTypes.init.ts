const defaultPageTypes = [{
    name: 'page',
    icon: 'regular-lightbulb',
}, {
    name: 'landingpage',
    icon: 'regular-dashboard',
}, {
    name: 'product_list',
    icon: 'regular-shopping-basket',
}, {
    name: 'product_detail',
    icon: 'regular-tag',
}];

/**
 * @private
 */
export default () => {
    const pageTypeService = Shopware.Service().get('cmsPageTypeService');

    defaultPageTypes.forEach((type: { name: string, icon: string }) => {
        pageTypeService.register(type);
    });
};
