
import template from 'src/app/component/atom/grid/sw-grid/sw-grid.html.twig';

export default Shopware.ComponentFactory.register('sw-grid', {
    props: {
        striped: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    template
});
