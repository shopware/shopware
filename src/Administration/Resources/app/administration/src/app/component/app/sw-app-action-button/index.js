import template from './sw-app-action-button.html.twig';
import './sw-app-action-button.scss';

const { Component, State, Context } = Shopware;

Component.register('sw-app-action-button', {
    template,

    inject: ['acl'],

    props: {
        action: {
            type: Object,
            required: true,
        },
    },

    computed: {
        buttonLabel() {
            const currentLocale = State.get('session').currentLocale;
            const fallbackLocale = Context.app.fallbackLocale;

            return this.action.label[currentLocale] || this.action.label[fallbackLocale] || '';
        },

        /**
         * @feature-deprecated (FEATURE_NEXT_14360) tag:v6.5.0 - The method "openInNewTab" will be removed because
         * every action button route will be a post
         */
        openInNewTab() {
            return !!this.action.openNewTab;
        },

        /**
         * @feature-deprecated (FEATURE_NEXT_14360) tag:v6.5.0 - The method "linkData" will be removed because
         * every action button route will be a post
         */
        linkData() {
            if (this.openInNewTab) {
                return {
                    target: '_blank',
                    href: this.action.url,
                };
            }

            return {};
        },
    },

    methods: {
        runAction() {
            /**
             * @feature-deprecated (FEATURE_NEXT_14360) tag:v6.5.0 - will be removed because
             * every action button route will be a post
             */
            if (this.openInNewTab) {
                return;
            }

            this.$emit('run-app-action', this.action.id);
        },
    },
});

