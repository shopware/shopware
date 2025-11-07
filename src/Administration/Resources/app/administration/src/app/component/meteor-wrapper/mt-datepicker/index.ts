import { MtDatepicker as MtDatepickerOriginal } from '@shopware-ag/meteor-component-library';
// eslint-disable-next-line max-len
import type { DateTimeOptions } from 'vue-i18n';
import template from './mt-datepicker.html.twig';

/**
 * @sw-package framework
 *
 * @private
 * @status ready
 * @description Wrapper component for mt-datepicker. Replaces the
 * datepicker with automatic language and formatting for the admin user.
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    components: {
        'mt-datepicker-original': MtDatepickerOriginal,
    },

    props: {
        /**
         * Sets the locale for the date picker.
         * This affects things like the language used for month names and weekdays.
         */
        locale: {
            type: String as PropType<string>,
            required: false,
        },

        /**
         * The format of the date picker.
         * You can use a string or a function to format the date.
         */
        format: {
            type: Function,
            required: false,
            default: undefined,
        },

        /**
         * Defines the time zone for the date picker.
         * Useful for adjusting date and time according to a specific timezone.
         */
        timeZone: {
            type: String as PropType<string>,
            required: false,
        },

        /**
         * Defines the type of the date picker.
         * Options: "date" (for selecting a date), or "datetime" (for selecting both).
         */
        dateType: {
            type: String as PropType<'date' | 'datetime' | 'time'>,
            required: false,
            default: 'datetime',
        },

        /**
         * Determines if the timepicker is in 24 or 12 hour format
         */
        is24: {
            type: Boolean as PropType<boolean>,
            required: false,
        },
    },

    computed: {
        userLocale(): string {
            return Shopware.Store.get('session').currentLocale || 'en-US';
        },

        userTimeZone() {
            return Shopware?.Store?.get('session')?.currentUser?.timeZone ?? 'UTC';
        },

        is24HourFormat(): boolean {
            if (this.is24) {
                return this.is24 as boolean;
            }

            const locale = Shopware.Store.get('session').currentLocale!;
            const formatter = new Intl.DateTimeFormat(locale, { hour: 'numeric' });
            const intlOptions = formatter.resolvedOptions();
            return !intlOptions.hour12;
        },

        formatterOptions(): DateTimeOptions {
            const defaultFormat = {
                hour12: !this.is24HourFormat,
                locale: this.userLocale,
            };

            let format: {
                year?: 'numeric';
                month?: '2-digit' | 'numeric';
                day?: '2-digit' | 'numeric';
                hour?: '2-digit' | 'numeric';
                minute?: '2-digit' | 'numeric';
            } = {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
            };

            if (this.dateType === 'time') {
                format = {
                    hour: '2-digit',
                    minute: '2-digit',
                };
            }

            if (this.dateType === 'date') {
                format = {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                };
            }

            return {
                ...defaultFormat,
                ...format,
            };
        },
    },

    methods: {
        customFormat(date: Date | string): string | Date | null {
            if (typeof date === 'string') {
                return this.handleStringFormat(date);
            }

            return this.formatDate(date);
        },

        formatDate(date: Date): string {
            const currentLocale = Shopware.Store.get('session').currentLocale || 'en-US';
            const formatter = new Intl.DateTimeFormat(currentLocale, this.formatterOptions);

            return formatter.format(new Date(date));
        },

        // Extract the format pattern from Intl.DateTimeFormat
        getLocaleDatePattern(): { parts: Array<{ type: string; value: string }>; separators: RegExp } {
            const currentLocale = Shopware.Store.get('session').currentLocale || 'en-US';
            const formatter = new Intl.DateTimeFormat(currentLocale, this.formatterOptions);

            // Use a known date to extract the pattern
            const sampleDate = new Date(2024, 0, 15, 14, 30, 0); // Jan 15, 2024, 14:30
            const parts = formatter.formatToParts(sampleDate);

            // Extract separator pattern
            const separatorChars = parts
                .filter((part) => part.type === 'literal')
                .map((part) => part.value.replace(/\s+/g, '\\s*'))
                .join('|');

            const separators = new RegExp(separatorChars || '[\\s,./:-]+', 'g');

            return { parts, separators };
        },

        // Get locale-specific AM/PM strings
        getLocaleTimePeriods(): { am: string; pm: string } {
            const currentLocale = Shopware.Store.get('session').currentLocale || 'en-US';
            const amFormatter = new Intl.DateTimeFormat(currentLocale, {
                hour: 'numeric',
                hour12: true,
            });
            const pmFormatter = new Intl.DateTimeFormat(currentLocale, {
                hour: 'numeric',
                hour12: true,
            });

            // Format a known AM time (1 AM)
            const amDate = new Date(2024, 0, 1, 1, 0, 0);
            const amParts = amFormatter.formatToParts(amDate);
            const amPeriod = amParts.find((part) => part.type === 'dayPeriod')?.value || 'AM';

            // Format a known PM time (1 PM)
            const pmDate = new Date(2024, 0, 1, 13, 0, 0);
            const pmParts = pmFormatter.formatToParts(pmDate);
            const pmPeriod = pmParts.find((part) => part.type === 'dayPeriod')?.value || 'PM';

            return { am: amPeriod, pm: pmPeriod };
        },

        // Parse date string according to pattern
        parseDateString(
            dateString: string,
            pattern: { parts: Array<{ type: string; value: string }>; separators: RegExp },
        ): {
            year?: number;
            month?: number;
            day?: number;
            hour?: number;
            minute?: number;
            dayPeriod?: string;
        } | null {
            try {
                // Normalize the input string
                const normalizedInput = dateString.trim();

                // Split by separators to get the individual values
                const values = normalizedInput.split(pattern.separators).filter((v) => v.length > 0);

                // Get only the non-literal parts (the actual date/time components)
                const dateParts = pattern.parts.filter((part) => part.type !== 'literal');

                if (values.length < dateParts.length) {
                    return null;
                }

                const result: {
                    year?: number;
                    month?: number;
                    day?: number;
                    hour?: number;
                    minute?: number;
                    dayPeriod?: string;
                } = {};

                let valueIndex = 0;

                dateParts.forEach((part) => {
                    const value = values[valueIndex];

                    switch (part.type) {
                        case 'year':
                            result.year = parseInt(value, 10);
                            // Handle 2-digit years
                            if (result.year < 100) {
                                result.year += result.year < 50 ? 2000 : 1900;
                            }
                            valueIndex += 1;
                            break;
                        case 'month':
                            result.month = parseInt(value, 10);
                            valueIndex += 1;
                            break;
                        case 'day':
                            result.day = parseInt(value, 10);
                            valueIndex += 1;
                            break;
                        case 'hour':
                            result.hour = parseInt(value, 10);
                            valueIndex += 1;
                            break;
                        case 'minute':
                            result.minute = parseInt(value, 10);
                            valueIndex += 1;
                            break;
                        case 'dayPeriod':
                            result.dayPeriod = value;
                            valueIndex += 1;
                            break;
                        default:
                            break;
                    }
                });

                return result;
            } catch (error) {
                return null;
            }
        },

        // Construct date with timezone
        constructDateWithTimezone(parsed: {
            year?: number;
            month?: number;
            day?: number;
            hour?: number;
            minute?: number;
            dayPeriod?: string;
        }): Date | null {
            try {
                // Validate parsed values
                if (parsed.month && (parsed.month < 1 || parsed.month > 12)) {
                    return null;
                }
                if (parsed.day && (parsed.day < 1 || parsed.day > 31)) {
                    return null;
                }
                if (parsed.hour !== undefined && (parsed.hour < 0 || parsed.hour > 23)) {
                    return null;
                }
                if (parsed.minute !== undefined && (parsed.minute < 0 || parsed.minute > 59)) {
                    return null;
                }

                let hour = parsed.hour ?? 0;

                // Handle 12-hour format with AM/PM
                if (parsed.dayPeriod) {
                    const timePeriods = this.getLocaleTimePeriods();

                    if (parsed.dayPeriod.toLowerCase() === timePeriods.pm.toLowerCase()) {
                        if (hour < 12) {
                            hour += 12;
                        }
                    } else if (parsed.dayPeriod.toLowerCase() === timePeriods.am.toLowerCase()) {
                        if (hour === 12) {
                            hour = 0;
                        }
                    }
                }

                // Create date in user's timezone
                // Note: JavaScript Date constructor uses local timezone by default
                const date = new Date(
                    parsed.year ?? new Date().getFullYear(),
                    (parsed.month ?? 1) - 1, // Month is 0-indexed in JS
                    parsed.day ?? 1,
                    hour,
                    parsed.minute ?? 0,
                    0,
                    0,
                );

                // Validate the constructed date
                if (Number.isNaN(date.getTime())) {
                    return null;
                }

                // Validate day is within month's valid range
                if (parsed.day && date.getDate() !== parsed.day) {
                    return null;
                }

                return date;
            } catch (error) {
                return null;
            }
        },

        // Parses a date string in the current user's locale and returns a date object
        handleStringFormat(dateString: string): Date | null {
            try {
                if (!dateString || dateString.trim().length === 0) {
                    return null;
                }

                const pattern = this.getLocaleDatePattern();
                const parsed = this.parseDateString(dateString, pattern);

                if (!parsed) {
                    return null;
                }

                const date = this.constructDateWithTimezone(parsed);

                if (!date || Number.isNaN(date.getTime())) {
                    return null;
                }

                return date;
            } catch (error) {
                console.warn('Failed to parse date string:', dateString, error);
                return null;
            }
        },
    },
});
