import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package checkout
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module and what it inherits from sw-extension-review-creation,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-extension-rating-modal',
    extends: 'sw-extension-review-creation',
    load: () => import('./src/sw-extension-rating-modal'),
    public: {
        props: [
            'extension',
        ],
        emits: [
            'modal-close',
            'created',
        ],
        blocks: [
            'sw_extension_review_creation',
            'sw_extension_rating_modal_slot_default',
            'sw_extension_rating_modal_slot_default_creation_inputs',
            'sw_extension_rating_modal_slot_footer',
            'sw_extension_rating_modal_slot_footer_gtc_checkbox',
            'sw_extension_rating_modal_slot_footer_buttons',
            'sw_extension_rating_modal_slot_footer_buttons_cancel',
            'sw_extension_rating_modal_slot_footer_buttons_submit',
            'sw_extension_review_creation_inputs',
            'sw_extension_review_creation_gtc_checkbox',
            'sw_extension_review_creation_buttons',
            'sw_extension_review_creation_buttons_submit_button',
        ],
        computed: [
            'currentUser',
            'userName',
            'installedVersion',
            'hasError',
            'disabled',
        ],
        methods: [
            'emitClose',
            'handleCreateReview',
            'createReview',
            'validateInputs',
            'validateHeadline',
            'validateRating',
            'onChange',
            'emitCreated',
        ],
    },
});
