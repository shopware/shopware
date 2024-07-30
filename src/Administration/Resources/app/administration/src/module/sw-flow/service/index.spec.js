/**
 * @package services-settings
 */

import './index';
import FlowBuilderService from './flow-builder.service';

const { Service } = Shopware;

describe('src/module/sw-flow/service/index.ts', () => {
    it('should register flowBuilderService', () => {
        const flowBuilderService = Service('flowBuilderService');

        expect(flowBuilderService).toBeDefined();
        expect(flowBuilderService).toBeInstanceOf(FlowBuilderService);
    });
});
