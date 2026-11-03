import { routeForSpec } from '../fake-index';
import { rewriteContext } from './context';

describe('rewriteContext', () => {
    it('reads the api and app context through functions, also through a destructured Context', () => {
        // ARRANGE
        const source = `const { Context } = Shopware;

export default {
    methods: {
        load() {
            Shopware.Context.api.languageId = this.languageId;
            return [Context.api, Shopware.Context.app.config.version];
        },
    },
};
`;

        // ACT
        const { code, dependencies } = routeForSpec(source, { use: [rewriteContext] });

        // ASSERT
        expect(code).toBe(`import { getApiContext, getAppContext } from '../dependencies';

export default {
    methods: {
        load() {
            getApiContext().languageId = this.languageId;
            return [getApiContext(), getAppContext().config.version];
        },
    },
};
`);
        expect(dependencies).toBe(`/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const getAppContext = () => Shopware.Context.app;
`);
    });

    it('leaves a context that is replaced as a whole', () => {
        // ARRANGE
        const source = 'Shopware.Context.api = { languageId: 1 };\n';

        // ACT
        const { code } = routeForSpec(source, { use: [rewriteContext] });

        // ASSERT
        expect(code).toBe(source);
    });
});
