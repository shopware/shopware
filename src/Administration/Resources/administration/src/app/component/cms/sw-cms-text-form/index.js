import template from './sw-cms-text-form.html.twig';

export default {
    name: 'sw-cms-text-form',
    template,

    props: {
        config: {
            type: Object
        },
        cmsSlot: {
            type: Object, // requires a block slot
            required: true
        }
    }
};
