import './component/templates/form-contact/register';
import './component/templates/form-newsletter/register';
import './component/templates/form-revocation-request/register';
import './config/register';
import './preview/register';
import './component/register';

/**
 * @private
 * @sw-package discovery
 */
Shopware.Service('cmsService').registerCmsElement({
    name: 'form',
    label: 'sw-cms.elements.form.label',
    component: 'sw-cms-el-form',
    configComponent: 'sw-cms-el-config-form',
    previewComponent: 'sw-cms-el-preview-form',
    defaultConfig: {
        type: {
            source: 'static',
            value: 'contact',
        },
        title: {
            source: 'static',
            value: '',
        },
        mailReceiver: {
            source: 'static',
            value: [],
        },
        defaultMailReceiver: {
            source: 'static',
            value: true,
        },
        confirmationText: {
            source: 'static',
            value: '',
        },
    },
});
