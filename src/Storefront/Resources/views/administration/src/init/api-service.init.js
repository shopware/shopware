import { Application } from 'src/core/shopware';
import SeoUrlTemplateService from '../core/service/api/seo-url-template.api.service';

Application.addServiceProviderDecorator('seoUrlTemplateService', (container) => {
    const initContainer = Application.getContainer('init');
    return new SeoUrlTemplateService(initContainer.httpClient, container.loginService);
});
