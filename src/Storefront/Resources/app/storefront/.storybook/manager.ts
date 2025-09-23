import { addons } from 'storybook/manager-api';
import './custom-controls.css';

addons.setConfig({
  rightPanelWidth: 800,
  sidebar: {
    showRoots: true,
  },
  actions: {
    showSidebar: false,
  },
});