import type { StorybookConfig } from '@storybook/server-webpack5';
import path from 'node:path';

const config: StorybookConfig = {
    stories: [
        '../../../views/components/**/*.stories.@(json)',
        '../../../../../../custom/plugins/*/src/Resources/views/components/**/*.stories.@(json)',
        '../../../../../../custom/plugins/*/Resources/views/components/**/*.stories.@(json)',
        '../../../../../../custom/apps/*/Resources/views/components/**/*.stories.@(json)',
    ],
    addons: ['@storybook/addon-webpack5-compiler-swc', '@storybook/addon-docs'],
    framework: {
        name: '@storybook/server-webpack5',
        options: {},
    },
    webpackFinal: (config) => {
        const jsonStoryRule = config.module?.rules?.find(
            (rule) => rule && typeof rule === 'object' && 'test' in rule && rule.test instanceof RegExp && rule.test.test('component.stories.json'),
        );

        if (!jsonStoryRule || typeof jsonStoryRule !== 'object') {
            throw new Error('Storybook JSON story loader rule was not found.');
        }

        jsonStoryRule.use = path.resolve(import.meta.dirname, './story-json-loader.cjs');

        return config;
    },
    env: (config) => ({
        ...config,
        APP_URL: process.env.APP_URL || 'http://localhost:8000',
    }),
};
export default config;
