const utils = Shopware.Utils;

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    beforeRouteEnter(to, _from, next) {
        if (!to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },
});
