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
    name: 'sw-extension-ratings-summary',
    load: () => import('./src/sw-extension-ratings-summary'),
    public: {
        props: [
            'summary',
        ],
        blocks: [
            'sw_extension_ratings_summary',
            'sw_extension_ratings_summary_grid',
            'sw_extension_ratings_summary_grid_average_rating',
            'sw_extension_ratings_summary_grid_label',
            'sw_extension_ratings_summary_grid_rating_rows',
            'sw_extension_ratings_summary_grid_rating_rows_content',
            'sw_extension_ratings_summary_grid_rating_progress_bars',
            'sw_extension_ratings_summary_grid_rating_progress_bars_count_rows',
            'sw_extension_ratings_summary_grid_rating_progress_bars_count_rows_count',
            'sw_extension_ratings_summary_grid_rating_progress_bars_progress_bar_rows',
            'sw_extension_ratings_summary_grid_rating_progress_bars_progress_bar_rows_progress_bar',
        ],
        computed: [
            'maxProgressValue',
        ],
    },
});
