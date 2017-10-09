
import template from 'src/app/component/atom/grid/sw-grid-col/sw-grid-col.html.twig';

export default Shopware.ComponentFactory.register('sw-grid-col', {
    inject: ['eventEmitter'],
    props: ['width', 'flex', 'editor', 'dataIndex'],
    computed: {
        colWidth() {
            if (this.width) {
                return { width: `${this.width}px`, flexGrow: 0 };
            }
            return { flexGrow: this.flex };
        }
    },

    methods: {
        startEditing(event) {
            if (!this.editable) {
                return false;
            }

            const target = event.target;
            this.$colOriginalContent = target.innerHTML;

            target.setAttribute('contenteditable', true);
            target.addEventListener('blur', this.finishEditing);
            this.$parent.$el.classList.add('is--editing');

            return true;
        },

        finishEditing(event) {
            const target = event.target;
            const rowId = this.$parent.$vnode.key;
            const newContent = target.innerHTML;

            target.removeEventListener('blur', this.finishEditing);
            target.removeAttribute('contenteditable');
            this.$parent.$el.classList.remove('is--editing');

            if (newContent === this.$colOriginalContent) {
                return false;
            }

            this.eventEmitter.emit('edit-column', {
                id: rowId,
                property: this.dataIndex,
                content: target.innerHTML.trim()
            });

            return true;
        }
    },

    template
});
