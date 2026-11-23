import template from './sw-shortcut-page.html.twig';

export default {
    template,

    shortcuts: {
        'SYSTEMKEY+S': {
            active() {
                return this.editable;
            },
            method: 'onSave',
        },
        ESCAPE: 'onCancel',
    },

    data() {
        return {
            editable: true,
            saved: false,
        };
    },

    methods: {
        onSave() {
            this.saved = true;
        },

        onCancel() {
            this.saved = false;
        },
    },
};
