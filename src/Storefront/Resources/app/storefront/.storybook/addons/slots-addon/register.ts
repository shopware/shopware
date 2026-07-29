import { addons, types } from 'storybook/manager-api';
import { SlotsPanel } from './SlotsPanel';

const ADDON_ID = 'slots-addon';
const PANEL_ID = `${ADDON_ID}/panel`;

addons.register(ADDON_ID, () => {
  addons.add(PANEL_ID, {
    type: types.PANEL,
    title: 'Slots',
    render: ({ active }) => {
      return active ? SlotsPanel() : null;
    },
  });
});
