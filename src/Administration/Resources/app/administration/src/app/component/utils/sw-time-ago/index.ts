import template from './sw-time-ago.html.twig';
import useUpdateClock from './updateClock';

/**
 * @private
 * @sw-package checkout
 * @description Render datetimes with relative values like "13 minutes ago" - works with dates in the past and future
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-time-ago date="2021-08-25T11:08:48.940+00:00"></sw-time-ago>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        date: {
            type: [
                Date,
                String,
            ] as PropType<Date | string>,
            required: true,
        },
        dateTimeFormat: {
            type: Object as PropType<Intl.DateTimeFormatOptions>,
            required: false,
            default: {},
        },
        mode: {
            type: String as PropType<'relative' | 'calendar'>,
            required: false,
            default: 'relative',
            validator(value: string) {
                return [
                    'relative',
                    'calendar',
                ].includes(value);
            },
        },
    },

    data(): {
        formattedRelativeTime: string | null;
        interval: ReturnType<typeof setInterval> | null;
        now: number;
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
            if (this.mode === 'calendar') {
                return this.dateFilter(this.dateObject.toString());
            }

            return this.dateFilter(this.dateObject.toString(), this.dateTimeFormat);
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

            return (
                this.dateObject.getDate() === today.getDate() &&
                this.dateObject.getMonth() === today.getMonth() &&
                this.dateObject.getFullYear() === today.getFullYear()
            );
        },
    },

    mounted() {
        // subscriber to the updater, which updates the formatted date every 30 seconds
        useUpdateClock(() => {
            // we have to set a new date, as vue does not react to changes in the date object
            // and does not invalidate the computed cache
            // this would lead to a wrong time string, if the component is active for more than 1 minute e.g.
            this.now = Date.now();
            this.formattedRelativeTime = this.formatRelativeTime();
        });
    },

    watch: {
        date() {
            this.formattedRelativeTime = this.formatRelativeTime();
        },
    },

    methods: {
        formatRelativeTime(): string {
            if (this.mode === 'calendar') {
                return this.formatCalendarTime();
            }

            const diff = Date.now() - this.dateObject.getTime();

            const secondsAgo = Math.round(diff / 1000);
            const minutesAgo = Math.round(secondsAgo / 60);

            if (diff >= 0) {
                if (this.lessThanOneMinute) {
                    return this.$t('global.sw-time-ago.justNow');
                }

                if (this.lessThanOneHour) {
                    return this.$t('global.sw-time-ago.minutesAgo', { minutesAgo }, minutesAgo);
                }
            } else {
                if (this.lessThanOneMinuteFromNow) {
                    return this.$t('global.sw-time-ago.aboutNow');
                }

                if (this.lessThanOneHourFromNow) {
                    const minutesFromNow = Math.abs(minutesAgo);
                    return this.$t('global.sw-time-ago.minutesFromNow', { minutesFromNow }, minutesFromNow);
                }
            }

            if (this.isToday) {
                return this.dateFilter(this.dateObject.toString(), {
                    year: undefined,
                    month: undefined,
                    day: undefined,
                });
            }

            return this.dateFilter(this.dateObject.toString(), this.dateTimeFormat);
        },

        formatCalendarTime(): string {
            const time = this.dateFilter(this.dateObject.toString(), {
                year: undefined,
                month: undefined,
                day: undefined,
            });

            if (this.isToday) {
                return this.$t('global.sw-time-ago.todayAt', { time });
            }

            const yesterday = new Date(Date.now());
            yesterday.setDate(yesterday.getDate() - 1);

            const dayBeforeYesterday = new Date(Date.now());
            dayBeforeYesterday.setDate(dayBeforeYesterday.getDate() - 2);

            if (this.isSameDay(this.dateObject, yesterday)) {
                return this.$t('global.sw-time-ago.yesterdayAt', { time });
            }

            if (this.isSameDay(this.dateObject, dayBeforeYesterday)) {
                return this.$t('global.sw-time-ago.dayBeforeYesterdayAt', { time });
            }

            const date = this.dateFilter(this.dateObject.toString(), {
                year: this.dateTimeFormat.year ?? 'numeric',
                month: this.dateTimeFormat.month ?? '2-digit',
                day: this.dateTimeFormat.day ?? '2-digit',
                hour: undefined,
                minute: undefined,
            });

            return this.$t('global.sw-time-ago.dateAtTime', { date, time });
        },

        isSameDay(date: Date, comparisonDate: Date): boolean {
            return (
                date.getDate() === comparisonDate.getDate() &&
                date.getMonth() === comparisonDate.getMonth() &&
                date.getFullYear() === comparisonDate.getFullYear()
            );
        },
    },
});
