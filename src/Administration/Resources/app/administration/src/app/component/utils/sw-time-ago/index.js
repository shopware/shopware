import template from './sw-time-ago.html.twig';

const { Component } = Shopware;

/**
 * @public
 * @description Render datetimes with relative values like "13 minutes ago"
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-time-ago date=""2021-08-25T11:08:48.940+00:00""></sw-time-ago>
 */
Component.register('sw-time-ago', {
    template,

    props: {
        date: {
            type: [Date, String],
            required: true,
        },
    },

    data() {
        return {
            formattedRelativeTime: null,
            interval: null,
        };
    },

    computed: {
        dateObject() {
            // when prop is string then convert it to date object
            if (typeof this.date === 'string') {
                return new Date(this.date);
            }

            return this.date;
        },

        dateFilter() {
            return Shopware.Filter.getByName('date');
        },

        fullDatetime() {
            return this.dateFilter(this.dateObject);
        },

        lessThanOneMinute() {
            const minute = 1000 * 60;
            const minuteAgo = Date.now() - minute;

            return this.dateObject.getTime() > minuteAgo;
        },

        lessThanOneHour() {
            const hour = 1000 * 60 * 60;
            const hourAgo = Date.now() - hour;

            return this.dateObject.getTime() > hourAgo;
        },

        isToday() {
            const today = new Date(Date.now());

            return this.dateObject.getDate() === today.getDate() &&
                this.dateObject.getMonth() === today.getMonth() &&
                this.dateObject.getFullYear() === today.getFullYear();
        },
    },

    mounted() {
        this.formattedRelativeTime = this.formatRelativeTime();

        // update the formatted date every 30 seconds
        this.interval = setInterval(() => {
            this.formattedRelativeTime = this.formatRelativeTime();
        }, 30000);
    },

    beforeDestroy() {
        if (this.interval) {
            clearInterval(this.interval);
        }
    },

    methods: {
        formatRelativeTime() {
            const secondsAgo = Math.round((new Date() - this.dateObject) / 1000);
            const minutesAgo = Math.round(secondsAgo / 60);

            if (this.lessThanOneMinute) {
                return this.$tc('global.sw-time-ago.justNow');
            }

            if (this.lessThanOneHour) {
                return this.$tc('global.sw-time-ago.minutesAgo', minutesAgo, { minutesAgo });
            }

            if (this.isToday) {
                return this.dateFilter(this.dateObject, {
                    year: undefined,
                    month: undefined,
                    day: undefined,
                });
            }

            return this.dateFilter(this.dateObject);
        },
    },
});
