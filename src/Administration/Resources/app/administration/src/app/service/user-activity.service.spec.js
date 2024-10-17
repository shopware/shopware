/**
 * @package admin
 */
import UserActivityService from './user-activity.service';

describe('src/app/service/user-activity.service.ts', () => {
    let service;

    beforeEach(() => {
        service = new UserActivityService();
    });

    afterEach(() => {
        localStorage.removeItem('lastActivity');
    });

    it('should instantiate', () => {
        expect(service instanceof UserActivityService).toBe(true);
    });

    it('should change last user activity', () => {
        const date = new Date();

        service.updateLastUserActivity(date);

        expect(service.getLastUserActivity().getTime()).toBe(date.getTime());
    });
});
