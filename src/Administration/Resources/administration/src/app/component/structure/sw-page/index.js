import { Component } from 'src/core/shopware';
import dom from 'src/core/service/utils/dom.utils';
import template from './sw-page.html.twig';
import './sw-page.less';

Component.register('sw-page', {
    template,

    data() {
        return {
            module: null,
            parentRoute: null,
            scrollbarOffset: 0
        };
    },

    computed: {
        pageColor() {
            return (this.module !== null) ? this.module.color : '#d8dde6';
        }
    },

    mounted() {
        this.initPage();
    },

    updated() {
        this.setScrollbarOffset();
    },

    methods: {
        initPage() {
            if (this.$route.meta.$module) {
                this.module = this.$route.meta.$module;
            }

            if (this.$route.meta.parentPath) {
                this.parentRoute = this.$route.meta.parentPath;
            }
        },

        setScrollbarOffset() {
            this.scrollbarOffset = dom.getScrollbarWidth(this.$refs.swPageContent.firstChild);
        }
    }
});
