const FilterFactory = Shopware.Classes._private.FilterFactory;

export default function initializeFilterFactory() {
    const filterFactory = new FilterFactory();
    this.addServiceProvider('filterFactory', () => {
        return filterFactory;
    });
}
