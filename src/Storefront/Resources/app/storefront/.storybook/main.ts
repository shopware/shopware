import type { StorybookConfig } from '@storybook/server-webpack5';

const config: StorybookConfig = {
  "stories": [
    "../../../views/components/**/*.stories.@(json)",
    "../../../../../../custom/plugins/*/src/Resources/views/components/**/*.stories.@(json)",
    "../../../../../../custom/plugins/*/Resources/views/components/**/*.stories.@(json)",
    "../../../../../../custom/apps/*/Resources/views/components/**/*.stories.@(json)",
  ],
  "addons": [
    "@storybook/addon-webpack5-compiler-swc",
    "@storybook/addon-docs",
  ],
  "framework": {
    "name": "@storybook/server-webpack5",
    "options": {},
  },
  "env": (config) => ({
    ...config,
    APP_URL: process.env.APP_URL || 'http://localhost:8000',
  }),
};
export default config;