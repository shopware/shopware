import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-settings-message-stats',
    load: () => import('./src/sw-settings-message-stats'),
    public: {
        blocks: [
            'sw_settings_message_stats',
        ],
        computed: [
            'statsData',
            'hasStats',
            'isStatsDisabled',
            'formattedProcessedSince',
            'formattedAverageTime',
            'statBlocks',
            'sortedMessageTypeStats',
        ],
        methods: [
            'createdComponent',
            'loadStats',
        ],
    },
});
