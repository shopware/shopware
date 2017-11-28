import './sw-card.less';
import template from './sw-card.html.twig';

Shopware.Component.register('sw-card', {
    props: {
        title: {
            type: String,
            required: true
        }
    },

    template
});
