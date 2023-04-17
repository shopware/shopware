import Vue from 'vue';
import UserConfigBaseClass from './user-config.class';

class UserConfigImplementation extends UserConfigBaseClass {
    static USER_CONFIG_KEY = 'favorites';

    state = Vue.observable({ favorites: [] });

    getFavoriteBlockNames() {
        return this.state.favorites;
    }

    isFavorite(elementName) {
        return this.state.favorites.includes(elementName);
    }

    update(state, elementName) {
        if (state && !this.isFavorite(elementName)) {
            this.state.favorites.push(elementName);
        } else if (!state && this.isFavorite(elementName)) {
            const index = this.state.favorites.indexOf(elementName);

            this.state.favorites.splice(index, 1);
        }

        this.saveUserConfig();
    }

    getConfigurationKey() {
        return this.USER_CONFIG_KEY;
    }

    async readUserConfig() {
        this.userConfig = await this.getUserConfig();
        this.state.favorites = this.userConfig.value;
    }

    setUserConfig() {
        this.userConfig.value = this.state.favorites;
    }
}

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
                key: UserConfigImplementation.USER_CONFIG_KEY,
                value: []
            }]
        }
    }
});

responses.addResponse({
    method: 'Post',
    url: '/user-config',
    status: 200,
    response: {
        data: []
    }
});


describe('src/Administration/Resources/app/administration/src/core/service/support/user-config.class.ts', () => {
    let service;

    beforeEach(() => {
        Shopware.State.get('session').currentUser = {
            id: '8fe88c269c214ea68badf7ebe678ab96'
        };

        service = new UserConfigImplementation();
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
            key: UserConfigImplementation.USER_CONFIG_KEY,
            value: []
        };

        const entity = service.createUserConfigEntity(UserConfigImplementation.USER_CONFIG_KEY);

        expect(entity).toMatchObject(expectedValues);
    });

    it('handleEmptyUserConfig > replaces the property "value" with an empty array', () => {
        const userConfigMock = {
            value: {}
        };

        service.handleEmptyUserConfig(userConfigMock);

        expect(Array.isArray(userConfigMock.value)).toBeTruthy();
    });

    it('getCriteria > returns a criteria including specific filters', () => {
        const criteria = service.getCriteria(UserConfigImplementation.USER_CONFIG_KEY);

        expect(criteria.filters).toContainEqual({ type: 'equals', field: 'key', value: UserConfigImplementation.USER_CONFIG_KEY });
        expect(criteria.filters).toContainEqual({ type: 'equals', field: 'userId', value: '8fe88c269c214ea68badf7ebe678ab96' });
    });

    it('getCurrentUserId > returns the userId of the current session user', () => {
        expect(service.getCurrentUserId()).toEqual('8fe88c269c214ea68badf7ebe678ab96');
    });
});
