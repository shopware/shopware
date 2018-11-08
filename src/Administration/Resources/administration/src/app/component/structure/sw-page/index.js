import { Component, Mixin } from 'src/core/shopware';
import template from './sw-page.html.twig';
import './sw-page.less';

Component.register('sw-page', {
    template,

    mixins: [
        Mixin.getByName('header-offsets')
    ],

    props: {
        showSmartBar: {
            type: Boolean,
            default: true
        }
    },

    data() {
        return {
            module: null,
            parentRoute: null
        };
    },

    computed: {
        pageColor() {
            return (this.module !== null) ? this.module.color : '#d8dde6';
        },

        pageContainerClasses() {
            return {
                'has--smart-bar': this.showSmartBar
            };
        },

        smartBarStyles() {
            return {
                'border-bottom-color': this.pageColor
            };
        }
    },

    mounted() {
        this.initPage();
    },

    methods: {
        initPage() {
            if (this.$route.meta.$module) {
                this.module = this.$route.meta.$module;
            }

            if (this.$route.meta.parentPath) {
                this.parentRoute = this.$route.meta.parentPath;
            }
        }
    }
});
