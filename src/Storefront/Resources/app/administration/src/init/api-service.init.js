import ThemeService from '../core/service/api/theme.api.service';

const { Application } = Shopware;

Application.addServiceProviderDecorator('themeService', (container) => {
    const initContainer = Application.getContainer('init');
    return new ThemeService(initContainer.httpClient, container.loginService);
});
