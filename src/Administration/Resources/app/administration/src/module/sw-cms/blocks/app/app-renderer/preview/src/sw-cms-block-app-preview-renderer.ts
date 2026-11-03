import template from './sw-cms-block-app-preview-renderer.html.twig';
import './sw-cms-block-app-preview-renderer.scss';
import { wrapComponentConfig } from '../dependencies';

/**
 * @private
 * @sw-package discovery
 */
export default wrapComponentConfig({
    template,

    props: {
        block: {
            type: Object as PropType<{
                previewImage?: string;
                appName?: string;
                label?: string;
            }>,
            required: false,
            default() {
                return {};
            },
        },
    },

    computed: {
        previewImage(): string | undefined {
            return this.block.previewImage;
        },

        blockLabel(): string {
            return this.block.label ?? '';
        },

        appName(): string {
            return this.block.appName ?? '';
        },
    },
});
