
import 'src/app/component/atom/grid/sw-grid-row-editing/sw-grid-row-editing.less';
import template from 'src/app/component/atom/grid/sw-grid-row-editing/sw-grid-row-editing.html.twig';

export default Shopware.Component.register('sw-grid-row-editing', {
    props: ['items'],

    methods: {
        onCancelEditing() {
            this.$emit('cancel-editing');
        },

        onSaveEditing() {
            this.$emit('save-editing', this.items);
        }
    },

    template
});
