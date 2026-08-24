/**
 * @sw-package framework
 */
import useSalutation from './use-salutation';

const entity = {
    salutation: { id: '1', salutationKey: 'mr', displayName: 'Mr' },
    title: '',
    firstName: 'John',
    lastName: 'Doe',
};

describe('src/app/composables/use-salutation', () => {
    let filter: jest.Mock;

    beforeEach(() => {
        filter = jest.fn().mockReturnValue('Mr John Doe');
        window.Shopware = {
            Filter: { getByName: jest.fn().mockReturnValue(filter) },
        } as unknown as typeof Shopware;
    });

    it('delegates to the salutation filter and returns its result', () => {
        const { salutation } = useSalutation();

        expect(salutation(entity, 'fallback')).toBe('Mr John Doe');
        expect(filter).toHaveBeenCalledWith(entity, 'fallback');
    });

    it('passes an empty fallback by default', () => {
        const { salutation } = useSalutation();

        salutation(entity);

        expect(filter).toHaveBeenCalledWith(entity, '');
    });
});
