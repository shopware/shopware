import template from 'src/app/component/atom/sw-card/sw-card.html.twig';
import 'src/app/component/atom/sw-card/sw-card.less';

export default Shopware.Component.register('sw-card', {
    props: {
        title: {
            type: String,
            required: true
        }
    },

    template
});
