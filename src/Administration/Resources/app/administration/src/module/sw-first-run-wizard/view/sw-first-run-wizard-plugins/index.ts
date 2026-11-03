import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@after-sales
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-first-run-wizard-plugins',
    load: () => import('./src/sw-first-run-wizard-plugins'),
    public: {
        emits: [
            'extension-activated',
            'frw-set-title',
            'buttons-update',
            'loading-finished',
        ],
        blocks: [
            'sw_first_run_wizard_plugins',
        ],
        computed: [
            'categoryLead',
            'notCategoryLead',
            'showSpacer',
            'showCategoryLead',
            'showNotCategoryLead',
        ],
        methods: [
            'createdComponent',
            'setTitle',
            'updateButtons',
            'regionVariant',
            'categoryVariant',
            'onSelectRegion',
            'onSelectCategory',
            'getRecommendations',
            'getRecommendationRegions',
            'reloadRecommendations',
        ],
    },
});
