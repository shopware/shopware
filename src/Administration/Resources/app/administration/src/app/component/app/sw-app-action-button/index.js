import template from './sw-app-action-button.html.twig';
import './sw-app-action-button.scss';

const { Component, State, Context } = Shopware;

Component.register('sw-app-action-button', {
    template,

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

        openInNewTab() {
            return !!this.action.openNewTab;
        },

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
            if (this.openInNewTab) {
                return;
            }

            this.$emit('run-app-action', this.action.id);
        },
    },
});

