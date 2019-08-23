import './component';
import './config';
import './preview';

const { Application } = Shopware;

Application.getContainer('service').cmsService.registerCmsElement({
    name: 'product-box',
    label: 'Product box',
    component: 'sw-cms-el-product-box',
    previewComponent: 'sw-cms-el-preview-product-box',
    configComponent: 'sw-cms-el-config-product-box',
    defaultConfig: {
        product: {
            source: 'static',
            value: null,
            required: true
        },
        boxLayout: {
            source: 'static',
            value: 'standard'
        },
        displayMode: {
            source: 'static',
            value: 'standard'
        },
        verticalAlign: {
            source: 'static',
            value: null
        }
    },
    defaultData: {
        boxLayout: 'standard',
        product: {
            name: 'Lorem Ipsum dolor',
            description: `Lorem ipsum dolor sit amet, consetetur sadipscing elitr,
                          sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat,
                          sed diam voluptua.`.trim(),
            price: [
                { gross: 19.90 }
            ],
            cover: {
                media: {
                    url: '/administration/static/img/cms/preview_glasses_large.jpg',
                    alt: 'Lorem Ipsum dolor'
                }
            }
        }
    }
});
