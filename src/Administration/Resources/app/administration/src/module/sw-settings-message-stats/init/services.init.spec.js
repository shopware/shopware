/**
 * @sw-package framework
 */

import MessageStatsApiService from '../../../core/service/api/message-stats.api.service';

describe('module/sw-settings-message-stats/init/services.init.js', () => {
    let messageStatsService;

    beforeAll(async () => {
        // Import the service initialization
        await import('./services.init');
    });

    beforeEach(() => {
        // Get the registered service
        messageStatsService = Shopware.Service('messageStatsService');
    });

    it('should register the messageStatsService', () => {
        expect(messageStatsService).toBeDefined();
        expect(messageStatsService).toBeInstanceOf(MessageStatsApiService);
    });

    it('should initialize messageStatsService with required dependencies', () => {
        expect(messageStatsService.httpClient).toBe(Shopware.Application.getContainer('init').httpClient);
        expect(messageStatsService.loginService).toBe(Shopware.Service('loginService'));
    });
}); 