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
    name: 'sw-extension-rating-stars',
    load: () => import('./src/sw-extension-rating-stars'),
    public: {
        props: [
            'editable',
            'size',
            'rating',
        ],
        emits: [
            'update:rating',
        ],
        blocks: [
            'sw_extension_rating_stars',
            'sw_extension_rating_stars_wrapper',
            'sw_extension_rating_stars_wrapper_stars',
            'sw_extension_rating_stars_wrapper_stars_full_star',
            'sw_extension_rating_stars_wrapper_stars_partial_star',
            'sw_extension_rating_stars_wrapper_stars_partial_star_wrapper',
        ],
        computed: [
            'editableClass',
            'sizeValue',
            'starSize',
            'partialStarSize',
            'partialStarWidth',
            'defaultSizeForEditable',
            'scaleFactor',
        ],
        methods: [
            'createdComponent',
            'colorClass',
            'addRating',
            'showPartialStar',
        ],
    },
});
