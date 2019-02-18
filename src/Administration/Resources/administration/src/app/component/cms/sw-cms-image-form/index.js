import template from './sw-cms-image-form.html.twig';

export default {
    name: 'sw-cms-image-form',
    template,

    props: {
        config: {
            type: Object,
            required: true
        },
        cmsSlot: {
            type: Object, // requires a block slot
            required: true
        }
    }
};
