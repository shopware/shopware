import 'src/app/component/base/sw-collapse';
import template from './sw-media-collapse.html.twig';
import './sw-media-collapse.scss';

Shopware.Component.extend('sw-media-collapse', 'sw-collapse', {
    template,

    props: {
        title: {
            type: String,
            required: true,
        },
    },

    computed: {
        expandButtonClass() {
            return {
                'is--hidden': this.expanded,
            };
        },
        collapseButtonClass() {
            return {
                'is--hidden': !this.expanded,
            };
        },
    },
});
