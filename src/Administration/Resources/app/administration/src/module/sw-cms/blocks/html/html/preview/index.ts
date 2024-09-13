import template from './sw-cms-preview-html.html.twig';
import './sw-cms-preview-html.scss';

/**
 * @private
 * @package buyers-experience
 */
export default {
    template,

    compatConfig: Shopware.compatConfig,

    data() {
        return {
            demoValue: `
<h2>Lorem ipsum dolor</h2>
<p>Lorem ipsum dolor sit amet</p>
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
