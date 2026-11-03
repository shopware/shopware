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
