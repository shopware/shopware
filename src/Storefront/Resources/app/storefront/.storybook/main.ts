import type { StorybookConfig } from '@storybook/server-webpack5';

const config: StorybookConfig = {
  "stories": [
    "../../../views/components/Sw/**/*.stories.@(json)"
  ],
  "addons": [
    "@storybook/addon-webpack5-compiler-swc",
    "@storybook/addon-docs",
    "./addons/slots-addon/register.ts",
    "./addons/template-addon/register.ts",
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