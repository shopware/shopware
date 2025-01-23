import template from './sw-cms-el-preview-html.html.twig';
import './sw-cms-el-preview-html.scss';

/**
 * @private
 * @sw-package discovery
 */
export default {
    template,

    compatConfig: Shopware.compatConfig,

    data() {
        return {
            demoValue: `
<h2>Lorem ipsum</h2>
<p>Lorem ipsum</p>
<button type="button">
    Click me!
</button>`.trim(),
            editorConfig: {
                highlightActiveLine: false,
                cursorStyle: 'slim',
                highlightGutterLine: false,
                showFoldWidgets: false,
            },
        };
    },
};
