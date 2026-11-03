import { isTestFixture } from './test-fixtures';

describe('isTestFixture', () => {
    it('tells a folder only the specs read from the files the component uses', () => {
        // ARRANGE
        const specs = ["const schema = path.join(__dirname, './_mock/entity-schema.json');\nimport '../sw-x.scss';"];
        const component = ["import template from './sw-x.html.twig';\nimport './sw-x.scss';"];

        // ACT
        const results = [
            '_mock',
            'sw-x.scss',
            'sw-x.html.twig',
            'unused.json',
        ].map((name) => isTestFixture(name, specs, component));

        // ASSERT
        expect(results).toEqual([
            true,
            false,
            false,
            false,
        ]);
    });
});
