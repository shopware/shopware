import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package checkout
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-extension-review-creation-inputs',
    load: () => import('./src/sw-extension-review-creation-inputs'),
    public: {
        props: [
            'errors',
        ],
        emits: [
            'changed',
        ],
        blocks: [
            'sw_extension_review_creation_inputs',
            'sw_extension_review_creation_inputs_grid',
            'sw_extension_review_creation_inputs_grid_headline_input',
            'sw_extension_review_creation_inputs_grid_rating_wrapper',
            'sw_extension_review_creation_inputs_grid_rating_wrapper_input',
            'sw_extension_review_creation_inputs_description_input',
        ],
    },
});
