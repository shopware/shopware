import { addons } from 'storybook/manager-api';
import './custom-controls.css';
import './addons/slots-addon/register';
import './addons/template-addon/register';

addons.setConfig({
  rightPanelWidth: 800,
  sidebar: {
    showRoots: true,
  },
  actions: {
    showSidebar: false,
  },
});