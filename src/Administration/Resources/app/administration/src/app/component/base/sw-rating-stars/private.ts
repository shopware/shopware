import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-rating-stars',
    load: () => import('./src/sw-rating-stars'),
    public: {
        props: [
            'value',
            'maxStars',
            'iconSize',
            'displayFractions',
        ],
        blocks: [
            'sw_rating_stars',
            'sw_rating_stars_placeholder',
            'sw_rating_stars_placeholder_stars',
            'sw_rating_stars_value',
            'sw_rating_stars_value_stars',
            'sw_rating_stars_value_stars_full',
            'sw_rating_stars_value_stars_partial',
        ],
        computed: [
            'ratingTooltip',
            'cappedValue',
            'partialStarCutStyle',
            'dynamicWidthStyle',
        ],
    },
});
