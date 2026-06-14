import template from './module-level-component.html.twig';
import './module-level-component.scss';

const { cloneDeep } = Shopware.Utils.object;

const COLORS = ['#FF0000', '#00FF00', '#0000FF'];

Shopware.Component.register('sw-module-level', {
    template,

    props: {
        name: {
            type: String,
            required: true,
        },
        items: {
            type: Array,
            required: false,
            default: () => [],
        },
    },

    computed: {
        clonedItems() {
            return cloneDeep(this.items);
        },

        nameColor() {
            return COLORS[this.name.length % COLORS.length];
        },
    },
});
