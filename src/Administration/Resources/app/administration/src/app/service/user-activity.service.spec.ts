import UserActivityService from './user-activity.service';

describe('src/app/service/user-activity.service.ts', () => {
    let service: UserActivityService | undefined;

    const cookieStorageMock = {};
    beforeEach(() => {
        // @ts-expect-error
        Shopware.Service = () => {
            return {
                getStorage: () => {
                    return {
                        // eslint-disable-next-line no-undef
                        setItem(key: string, value: unknown) {
                            cookieStorageMock[key] = value;
                        }
                    };
                }
            };
        };
        service = new UserActivityService();
    });

    it('should instantiate', () => {
        expect(service instanceof UserActivityService).toBe(true);
    });

    it('should change last user activity', () => {
        const date = new Date();
        const expectedResult = Math.round(+date / 1000);

        service.updateLastUserActivity(date);
        // @ts-expect-error
        expect(cookieStorageMock.lastActivity).toBe(`${expectedResult}`);
    });
});
