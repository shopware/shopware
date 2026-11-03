import { findLocalMixins } from './find-local-mixins';

describe('findLocalMixins', () => {
    it('lists the mixins whose registration no file exports, also through a nested destructure of the global', () => {
        // ARRANGE
        const code = `const {
    Mixin,
    Data: { Criteria },
} = Shopware;
export default {
    mixins: [Mixin.getByName('sw-settings-list'), Shopware.Mixin.getByName('notification'), Mixin.getByName(name)],
};
`;

        // ACT
        const names = findLocalMixins(code, (name) => name === 'sw-settings-list');

        // ASSERT
        expect(names).toEqual(['sw-settings-list']);
    });
});
