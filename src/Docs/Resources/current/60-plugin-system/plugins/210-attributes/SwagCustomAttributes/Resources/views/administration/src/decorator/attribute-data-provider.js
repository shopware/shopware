import { Application } from 'src/core/shopware';
import '../app/component/swag-attribute-type-radio';

Application.addServiceProviderDecorator('attributeDataProviderService', (attributeService) => {
    attributeService.upsertType('swagRadio', {
        configRenderComponent: 'swag-attribute-type-radio',
        type: 'string',
        config: {
            componentName: 'swag-radio',
            variant: 'pill'
        }
    });

    return attributeService;
});
