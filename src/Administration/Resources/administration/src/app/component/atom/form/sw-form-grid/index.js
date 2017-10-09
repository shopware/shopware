import template from './sw-form-grid.html.twig';
import './sw-form-grid.less';

export default Shopware.ComponentFactory.register('sw-form-grid', {
    props: {
        columns: Array,
        items: Array
    },

    computed: {

    },

    methods: {

    },

    template
});
