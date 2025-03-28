/**
 * @private
 * @sw-package discovery
 */
import './component';
import './config';
import './preview';

Shopware.Service('cmsService').registerCmsElement({
    name: 'popup-element',
    label: {
        'de-DE': 'Popup',
        'en-GB': 'Popup'
    },
    component: 'sw-cms-el-popup',
    configComponent: 'sw-cms-el-config-popup',
    previewComponent: 'sw-cms-el-preview-popup',
    defaultConfig: {
        title: { source: 'static', value: 'Popup Title' },
        content: { source: 'static', value: 'Popup Content' },
        buttonText: { source: 'static', value: 'Open Popup' },
        width: { source: 'static', value: '400px' },
        height: { source: 'static', value: 'auto' },
        trigger: { source: 'static', value: 'click' }
    },
    collect: function collect(elem) {
        const context = {
            title: elem.config.title.value,
            content: elem.config.content.value,
            buttonText: elem.config.buttonText.value
        };

        return context;
    }
});