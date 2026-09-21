import template from './sw-cms-el-html.html.twig';
import './sw-cms-el-html.scss';

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    mixins: [
        Shopware.Mixin.getByName('cms-element'),
    ],

    data() {
        return {
            editorConfig: {
                highlightActiveLine: false,
                cursorStyle: 'slim',
                highlightGutterLine: false,
                showFoldWidgets: false,
            },
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('html');
        },
    },
};
