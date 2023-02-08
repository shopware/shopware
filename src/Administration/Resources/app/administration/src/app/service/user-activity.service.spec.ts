import UserActivityService from './user-activity.service';

describe('src/app/service/user-activity.service.ts', () => {
    let service: UserActivityService | undefined;

    beforeEach(() => {
        service = new UserActivityService();
    });

    it('should instantiate', () => {
        expect(service instanceof UserActivityService).toBe(true);
    });

    it('should change last user activity', () => {
        Shopware.Context.app.lastActivity = 0;
        const date = new Date();
        const expectedResult = Math.round(+date / 1000);

        service.updateLastUserActivity(date);
        expect(Shopware.Context.app.lastActivity).toBe(expectedResult);
    });
});
