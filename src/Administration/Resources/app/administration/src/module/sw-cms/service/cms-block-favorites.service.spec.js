/**
 * @package buyers-experience
 */
import CmsBlockFavorites from 'src/module/sw-cms/service/cms-block-favorites.service';

const responses = global.repositoryFactoryMock.responses;

responses.addResponse({
    method: 'Post',
    url: '/search/user-config',
    status: 200,
    response: {
        data: {
            data: [{
                id: '8badf7ebe678ab968fe88c269c214ea6',
                userId: '8fe88c269c214ea68badf7ebe678ab96',
                key: CmsBlockFavorites.USER_CONFIG_KEY,
                value: [],
            }],
        },
    },
});

responses.addResponse({
    method: 'Post',
    url: '/user-config',
    status: 200,
    response: {
        data: [],
    },
});

describe('module/sw-cms/service/cms-block-favorites.service.spec.js', () => {
    let service;

    beforeEach(() => {
        Shopware.State.get('session').currentUser = {
            id: '8fe88c269c214ea68badf7ebe678ab96',
        };

        service = new CmsBlockFavorites();
    });

    it('getFavoriteBlockNames > should return favorites from internal state', () => {
        const expected = ['foo', 'bar'];
        service.state.favorites = expected;

        expect(service.getFavoriteBlockNames()).toEqual(expected);
    });

    it('isFavorite > checks if given string is included in favorites', () => {
        const expected = 'bar';
        service.state.favorites = ['foo', 'bar'];

        expect(service.isFavorite(expected)).toBeTruthy();
    });

    it('update > pushes new item to favorites and calls "saveUserConfig"', () => {
        const newItem = 'biz';

        service.saveUserConfig = jest.fn();
        service.state.favorites = ['foo', 'bar'];

        service.update(true, newItem);

        expect(service.isFavorite(newItem)).toBeTruthy();
        expect(service.saveUserConfig).toHaveBeenCalled();
    });

    it('update > removes existing item from favorites and calls "saveUserConfig"', () => {
        const removedItem = 'bar';

        service.saveUserConfig = jest.fn();
        service.state.favorites = ['foo', 'bar'];

        service.update(false, removedItem);

        expect(service.isFavorite(removedItem)).toBeFalsy();
        expect(service.saveUserConfig).toHaveBeenCalled();
    });

    it('update > does not add or remove items with a wrong state', () => {
        const existingItem = 'foo';
        const nonExistingItem = 'biz';

        service.state.favorites = ['foo', 'bar'];

        service.update(false, nonExistingItem);
        expect(service.isFavorite(nonExistingItem)).toBeFalsy();

        service.update(true, existingItem);
        expect(service.isFavorite(existingItem)).toBeTruthy();
    });

    it('createUserConfigEntity > entity has specific values', () => {
        const expectedValues = {
            userId: Shopware.State.get('session').currentUser.id,
            key: CmsBlockFavorites.USER_CONFIG_KEY,
            value: [],
        };

        const entity = service.createUserConfigEntity(CmsBlockFavorites.USER_CONFIG_KEY);

        expect(entity).toMatchObject(expectedValues);
    });

    it('handleEmptyUserConfig > replaces the property "value" with an empty array', () => {
        const userConfigMock = {
            value: {},
        };

        service.handleEmptyUserConfig(userConfigMock);

        expect(Array.isArray(userConfigMock.value)).toBeTruthy();
    });

    it('getCriteria > returns a criteria including specific filters', () => {
        const criteria = service.getCriteria(CmsBlockFavorites.USER_CONFIG_KEY);

        expect(criteria.filters).toContainEqual({ type: 'equals', field: 'key', value: CmsBlockFavorites.USER_CONFIG_KEY });
        expect(criteria.filters).toContainEqual({ type: 'equals', field: 'userId', value: '8fe88c269c214ea68badf7ebe678ab96' });
    });

    it('getCurrentUserId > returns the userId of the current session user', () => {
        expect(service.getCurrentUserId()).toBe('8fe88c269c214ea68badf7ebe678ab96');
    });
});
