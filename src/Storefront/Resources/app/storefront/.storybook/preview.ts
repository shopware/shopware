import type { Preview, StoryContext } from '@storybook/server-webpack5';

// Get the app URL from environment variables (injected by Storybook)
const appUrl = process.env.APP_URL;
const storybookApiUrl = `${appUrl}/storybook`;

const fetchStoryHtml = async (
    url: string,
    path: string,
    params: Record<string, unknown>,
    storyContext: StoryContext,
): Promise<string> => {
    const props: Record<string, unknown> = {};
    const blocks: Record<string, unknown> = {};

    Object.entries({ ...storyContext.globals, ...params }).forEach(([key, value]) => {
        const blockName = /^blocks\.([a-zA-Z_][a-zA-Z0-9_]*)$/.exec(key)?.[1];
        if (blockName) {
            blocks[blockName] = value;
            return;
        }

        props[key] = value;
    });

    const response = await fetch(`${url}/${path}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ props, blocks }),
    });

    if (!response.ok) {
      const decodeHtml = document.createElement('textarea');
      decodeHtml.innerHTML = await response.text();
      throw new Error(decodeHtml.value);
    }

    return response.text();
};

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
      fetchStoryHtml,
    },
  },
};

export default preview;
