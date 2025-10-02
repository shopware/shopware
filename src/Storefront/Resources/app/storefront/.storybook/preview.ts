import type { Preview } from '@storybook/server-webpack5'

// Get the app URL from environment variables (injected by Storybook)
const appUrl = process.env.APP_URL;
const storybookApiUrl = `${appUrl}/storybook`;

const preview: Preview = {
  parameters: {
    controls: {
      matchers: {
        color: /(background|color)$/i,
        date: /Date$/i,
      },
      expanded: true,
      sort: 'requiredFirst',
    },
    docs: {
      enabled: true,
    },
    layout: 'padded',
    toolbar: {
      'storybook/outline': { hidden: false },
    },
    options: {
      panelPosition: 'right',
    },
    server: {
      url: storybookApiUrl,
    },
  },
};

export default preview;