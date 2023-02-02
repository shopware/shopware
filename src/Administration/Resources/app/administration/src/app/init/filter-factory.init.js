/**
 * @package admin
 */

const FilterFactory = Shopware.Classes._private.FilterFactory;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeFilterFactory() {
    const filterFactory = new FilterFactory();
    this.addServiceProvider('filterFactory', () => {
        return filterFactory;
    });
}
