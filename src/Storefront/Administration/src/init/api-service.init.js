import { Application } from 'src/core/shopware';
import SeoUrlTemplateService from '../core/service/api/seo-url-template.api.service';
import SeoUrlService from '../core/service/api/seo-url.api.service';

Application.addServiceProviderDecorator('seoUrlService', (container) => {
    const initContainer = Application.getContainer('init');
    return new SeoUrlService(initContainer.httpClient, container.loginService);
});

Application.addServiceProviderDecorator('seoUrlTemplateService', (container) => {
    const initContainer = Application.getContainer('init');
    return new SeoUrlTemplateService(initContainer.httpClient, container.loginService);
});
