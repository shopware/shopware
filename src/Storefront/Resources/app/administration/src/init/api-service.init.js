/**
 * @sw-package discovery
 */
import ThemeService from '../core/service/api/theme.api.service';

const { Application } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Shopware.Service().register('themeService', (container) => {
    const initContainer = Application.getContainer('init');
    return new ThemeService(initContainer.httpClient, container.loginService);
});
