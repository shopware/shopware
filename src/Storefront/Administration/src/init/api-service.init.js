import SeoUrlTemplateService from '../core/service/api/seo-url-template.api.service';
import SeoUrlService from '../core/service/api/seo-url.api.service';
import ThemeService from '../core/service/api/theme.api.service';

const { Application } = Shopware;

Application.addServiceProviderDecorator('seoUrlService', (container) => {
    const initContainer = Application.getContainer('init');
    return new SeoUrlService(initContainer.httpClient, container.loginService);
});

Application.addServiceProviderDecorator('seoUrlTemplateService', (container) => {
    const initContainer = Application.getContainer('init');
    return new SeoUrlTemplateService(initContainer.httpClient, container.loginService);
});

Application.addServiceProviderDecorator('themeService', (container) => {
    const initContainer = Application.getContainer('init');
    return new ThemeService(initContainer.httpClient, container.loginService);
});
