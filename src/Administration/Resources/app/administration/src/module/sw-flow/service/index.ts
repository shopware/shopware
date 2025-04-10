import type { SubContainer } from 'src/global.types';
import FlowBuilderService from './flow-builder.service';

const { Application } = Shopware;

/**
 * @private
 * @sw-package after-sales
 */
declare global {
    interface ServiceContainer extends SubContainer<'service'> {
        flowBuilderService: FlowBuilderService;
    }
}

Application.addServiceProvider('flowBuilderService', () => {
    return new FlowBuilderService();
});
