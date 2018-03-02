import { Application, Filter } from 'src/core/shopware';

Filter.register('asset', (value) => {
    const initContainer = Application.getContainer('init');
    const context = initContainer.contextService;

    if (!value) {
        return '';
    }

    return `${context.assetsPath}${value}`;
});
