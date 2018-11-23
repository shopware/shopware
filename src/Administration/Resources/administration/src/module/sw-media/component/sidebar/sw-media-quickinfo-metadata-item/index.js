import { Component } from 'src/core/shopware';
import template from './sw-media-quickinfo-metadata-item.html.twig';
import './sw-media-quickinfo-metadata-item.less';

Component.register('sw-media-quickinfo-metadata-item', {
    template,

    props: {
        labelName: {
            required: true,
            type: String
        },
        value: {
            required: false,
            type: [String, Number]
        },
        suffix: {
            required: false,
            default: '',
            type: String
        },
        truncateMiddle: {
            required: false,
            default: false,
            type: Boolean
        }
    },

    data() {
        return {
            lastContent: ''
        };
    },

    computed: {
        getLabel() {
            return this.$tc(`sw-media.sidebar.metadata.${this.labelName}`);
        },

        getValue() {
            if (!this.value) {
                return 'unknown';
            }

            return this.value + this.suffix;
        }
    },

    mounted() {
        this.componentMounted();
    },

    updated() {
        this.componentUpdated();
    },

    methods: {
        componentMounted() {
            this.computeLastContent();
        },

        componentUpdated() {
            this.computeLastContent();
        },

        computeLastContent() {
            const el = this.$refs.value;
            if (this.truncateMiddle && el.offsetWidth < el.scrollWidth) {
                this.lastContent = `${this.getValue.slice(-7)}`;
                return;
            }

            this.lastContent = '';
        }
    }
});
