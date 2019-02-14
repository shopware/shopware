import cmsService from 'src/module/sw-cms/service/cms.service';
import './component';

cmsService.registerCmsElement({
    name: 'product-box',
    label: 'Product box',
    component: 'sw-cms-el-product-box',
    previewComponent: '',
    configComponent: ''
});
