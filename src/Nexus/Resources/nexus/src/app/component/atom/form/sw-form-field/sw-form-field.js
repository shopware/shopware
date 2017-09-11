
import 'src/app/component/atom/form/sw-form-field/sw-form-field.less';
import template from 'src/app/component/atom/form/sw-form-field/sw-form-field.html.twig';

export default Shopware.ComponentFactory.register('sw-form-field', {
    props: {
        id: {
            type: String,
            required: true
        },
        type: {
            type: String,
            default: 'text'
        },
        name: {
            type: String,
            required: true
        },
        placeholder: {
            type: String,
            default: ''
        },
        value: {
            type: [String, Boolean],
            default: '',
            sync: true
        },
        label: {
            type: String,
            default: ''
        }
    },
    template
});
