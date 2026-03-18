import { h } from 'vue';

Shopware.Component.register('sw-render-component', {
    props: {
        tag: {
            type: String,
            default: 'div',
        },
        label: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            isActive: false,
        };
    },

    render() {
        return h(this.tag, {
            class: { 'is-active': this.isActive },
            onClick: () => {
                this.isActive = !this.isActive;
            },
        }, this.label);
    },
});
