import { Application } from 'src/core/shopware';
import '../app/component/swag-custom-field-type-radio';

Application.addServiceProviderDecorator('customFieldDataProviderService', (customFieldService) => {
    customFieldService.upsertType('swagRadio', {
        configRenderComponent: 'swag-custom-field-type-radio',
        type: 'string',
        config: {
            componentName: 'swag-radio',
            variant: 'pill'
        }
    });

    return customFieldService;
});
