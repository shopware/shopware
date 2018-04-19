import ComponentFactory from 'src/core/factory/component.factory';
import template from 'src/app/component/atom/grid/sw-grid-row-editing/sw-grid-row-editing.html.twig';

export default ComponentFactory.register('sw-grid-row-editing', {
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
