import { addons, types } from 'storybook/manager-api';
import { TemplatePanel } from './TemplatePanel';

const ADDON_ID = 'template-addon';
const PANEL_ID = `${ADDON_ID}/panel`;

addons.register(ADDON_ID, () => {
  addons.add(PANEL_ID, {
    type: types.PANEL,
    title: 'Template',
    render: ({ active }) => {
      return active ? TemplatePanel() : null;
    },
  });
});
