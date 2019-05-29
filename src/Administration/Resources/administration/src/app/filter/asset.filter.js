import { Application, Filter } from 'src/core/shopware';

Filter.register('asset', (value) => {
    if (!value) {
        return '';
    }

    const initContainer = Application.getContainer('init');
    const context = initContainer.contextService;

    return `${context.assetsPath}${value}`;
});
