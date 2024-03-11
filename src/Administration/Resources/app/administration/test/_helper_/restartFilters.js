export default function restartFilters() {
    const filterRegistry = Shopware.Filter.getRegistry();
    filterRegistry.forEach((value, key) => {
        global.Vue.filter(key, value);
    });
}
