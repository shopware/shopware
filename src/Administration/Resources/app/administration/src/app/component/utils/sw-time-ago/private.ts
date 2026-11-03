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
    name: 'sw-time-ago',
    load: () => import('./src/sw-time-ago'),
    public: {
        props: [
            'date',
            'dateTimeFormat',
        ],
        blocks: [
            'sw_time_ago',
        ],
        computed: [
            'dateObject',
            'dateFilter',
            'fullDatetime',
            'lessThanOneMinute',
            'lessThanOneHour',
            'lessThanOneMinuteFromNow',
            'lessThanOneHourFromNow',
            'isToday',
        ],
        methods: [
            'formatRelativeTime',
        ],
    },
});
