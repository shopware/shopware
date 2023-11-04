import template from './sw-dashboard-index.html.twig';
import './sw-dashboard-index.scss';

/**
 * @package merchant-services
 *
 * @private
 */
export default {
    template,

    data() {
        return {
            cachedHeadlineGreetingKey: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        welcomeMessage() {
            const greetingName = this.greetingName;
            const welcomeMessage = this.$tc(
                this.cachedHeadlineGreetingKey,
                1,
                { greetingName },
            );

            // in the headline we want to greet the user by his firstname
            // if his first name is not available, we remove the personalized greeting part
            // but we want to make sure the punctuation like `.`, `!` or `?` is kept
            // for example "Still awake, ?" -> "Still awake?"…
            if (!greetingName) {
                return welcomeMessage.replace(/\,\s*/, '');
            }

            return welcomeMessage;
        },

        welcomeSubline() {
            return this.$tc(this.getGreetingTimeKey('daytimeWelcomeText'));
        },

        greetingName() {
            const { currentUser } = Shopware.State.get('session');

            // if currentUser?.firstName returns a loose falsy value
            // like `""`, `0`, `false`, `null`, `undefined`
            // we want to use `null` in the ongoing process chain,
            // otherwise we would need to take care of `""` and `null`
            // or `undefined` in tests and other places
            return currentUser?.firstName || null;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            Shopware.ExtensionAPI.publishData({
                id: 'sw-dashboard-detail__todayOrderData',
                path: 'todayOrderData',
                scope: this,
            });
            Shopware.ExtensionAPI.publishData({
                id: 'sw-dashboard-detail__statisticDateRanges',
                path: 'statisticDateRanges',
                scope: this,
            });

            this.cachedHeadlineGreetingKey = this.cachedHeadlineGreetingKey ?? this.getGreetingTimeKey('daytimeHeadline');
        },

        /**
         * getGreetingTimeKey reads through the existing dictionary and returns a localtime aware
         * `$tc ()` compatible String. The timebased dictionary keys look like `5h` or `11h` or `16h`
         * and contains an array with different greeting messages.
         * @param {String} type either 'daytimeHeadline' or 'daytimeWelcomeText'
         * @returns {String}
         */
        getGreetingTimeKey(type = 'daytimeHeadline') {
            const translateKey = `sw-dashboard.introduction.${type}`;
            const greetings = this.getGreetings(type);
            const hourNow = new Date().getHours();

            if (greetings === undefined) {
                return '';
            }

            // to find the right timeslot, we user array.find() which will stop after first match
            // for that reason the greetingTimes must be ordered from latest to earliest hour
            const greetingTimes = Object.keys(greetings)
                .map(entry => parseInt(entry.replace('h', ''), 10))
                .sort((a, b) => a - b)
                .reverse();

            /* find the current time slot */
            const greetingTime = greetingTimes.find(time => hourNow >= time) || greetingTimes[0];
            const greetingIndex = Math.floor(Math.random() * greetings[`${greetingTime}h`].length);

            return `${translateKey}.${greetingTime}h[${greetingIndex}]`;
        },

        getGreetings(type = 'daytimeHeadline') {
            const i18nMessages = this.$i18n.messages;

            const localeGreetings = i18nMessages?.[this.$i18n.locale]?.['sw-dashboard']?.introduction?.[type];
            const fallbackGreetings = i18nMessages?.[this.$i18n.fallbackLocale]?.['sw-dashboard']?.introduction?.[type];

            return localeGreetings ?? fallbackGreetings;
        },
    },
};
