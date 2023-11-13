import type { PropType } from 'vue';
import template from './sw-time-ago.html.twig';

const { Component } = Shopware;

/**
 * @deprecated tag:v6.6.0 - Will be private
 * @public
 * @description Render datetimes with relative values like "13 minutes ago" - works with dates in the past and future
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-time-ago date=""2021-08-25T11:08:48.940+00:00""></sw-time-ago>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-time-ago', () => Component.wrapComponentConfig({
    template,

    props: {
        date: {
            type: [Date, String] as PropType<Date|string>,
            required: true,
        },
    },

    data(): {
        formattedRelativeTime: string|null,
        interval: ReturnType<typeof setInterval>|null,
        now: number,
        } {
        return {
            formattedRelativeTime: null,
            interval: null,
            now: Date.now(),
        };
    },

    computed: {
        dateObject(): Date {
            // when prop is string then convert it to date object
            if (typeof this.date === 'string') {
                return new Date(this.date);
            }

            return this.date;
        },

        dateFilter() {
            return Shopware.Filter.getByName('date');
        },

        fullDatetime(): string {
            return this.dateFilter(this.dateObject.toString());
        },

        lessThanOneMinute(): boolean {
            const minute = 1000 * 60;
            const minuteAgo = this.now - minute;

            return this.dateObject.getTime() > minuteAgo;
        },

        lessThanOneHour(): boolean {
            const hour = 1000 * 60 * 60;
            const hourAgo = this.now - hour;

            return this.dateObject.getTime() > hourAgo;
        },

        lessThanOneMinuteFromNow(): boolean {
            const minute = 1000 * 60;
            const minuteAfter = this.now + minute;

            return this.dateObject.getTime() < minuteAfter;
        },

        lessThanOneHourFromNow(): boolean {
            const hour = 1000 * 60 * 60;
            const hourAfter = this.now + hour;

            return this.dateObject.getTime() < hourAfter;
        },

        isToday(): boolean {
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
            // we have to set a new date, as vue does not react to changes in the date object
            // and does not invalidate the computed cache
            // this would lead to a wrong time string, if the component is active for more than 1 minute e.g.
            this.now = Date.now();
            this.formattedRelativeTime = this.formatRelativeTime();
        }, 30000);
    },

    beforeDestroy() {
        if (this.interval) {
            clearInterval(this.interval);
        }
    },

    methods: {
        formatRelativeTime(): string {
            const diff = Date.now() - this.dateObject.getTime();

            const secondsAgo = Math.round(diff / 1000);
            const minutesAgo = Math.round(secondsAgo / 60);

            if (diff >= 0) {
                if (this.lessThanOneMinute) {
                    return this.$tc('global.sw-time-ago.justNow');
                }

                if (this.lessThanOneHour) {
                    return this.$tc('global.sw-time-ago.minutesAgo', minutesAgo, { minutesAgo });
                }
            } else {
                if (this.lessThanOneMinuteFromNow) {
                    return this.$tc('global.sw-time-ago.aboutNow');
                }

                if (this.lessThanOneHourFromNow) {
                    const minutesFromNow = Math.abs(minutesAgo);
                    return this.$tc('global.sw-time-ago.minutesFromNow', minutesFromNow, { minutesFromNow });
                }
            }

            if (this.isToday) {
                return this.dateFilter(this.dateObject.toString(), {
                    year: undefined,
                    month: undefined,
                    day: undefined,
                });
            }

            return this.dateFilter(this.dateObject.toString());
        },
    },
}));
