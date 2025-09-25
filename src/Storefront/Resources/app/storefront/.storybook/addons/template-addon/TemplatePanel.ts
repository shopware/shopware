import React from 'react';
import { useParameter, useStorybookApi } from 'storybook/manager-api';

export const TemplatePanel = () => {
  const [template, setTemplate] = React.useState<string>('');
  const [loading, setLoading] = React.useState<boolean>(true);
  const api = useStorybookApi();
  const templateParameter = useParameter('template', '');

  // Use the proper Storybook hooks to get template data
  React.useEffect(() => {
    if (typeof templateParameter === 'string' && templateParameter.length > 0) {
      setTemplate(templateParameter);
      setLoading(false);
    } else {
      // Try to get current story data from API
      try {
        const currentStory = api.getCurrentStoryData?.();
        if (currentStory?.parameters?.template) {
          setTemplate(currentStory.parameters.template);
        } else {
          setTemplate('');
        }
        setLoading(false);
      } catch (error) {
        setTemplate('');
        setLoading(false);
      }
    }
  }, [templateParameter, api]);

  if (loading) {
    return React.createElement('div', { 
      style: { 
        padding: '16px', 
        textAlign: 'center', 
        color: '#666' 
      } 
    }, 'Loading template...');
  }

  if (!template) {
    return React.createElement('div', { 
      style: { 
        padding: '16px', 
        textAlign: 'center', 
        color: '#666',
        fontStyle: 'italic'
      } 
    }, 'No template information available for this component');
  }

  return React.createElement('div', { style: { padding: '16px' } },
    React.createElement('h2', { 
      style: { 
        margin: '0 0 16px 0', 
        color: '#333',
        fontSize: '16px',
        fontWeight: 'bold'
      } 
    }, 'Template Example'),
    
    React.createElement('pre', {
      style: {
        margin: '0',
        padding: '16px',
        backgroundColor: '#f5f5f5',
        border: '1px solid #e0e0e0',
        borderRadius: '8px',
        fontSize: '12px',
        fontFamily: 'monospace',
        lineHeight: '1.5',
        overflow: 'auto',
        whiteSpace: 'pre-wrap',
        wordBreak: 'break-word'
      }
    }, template)
  );
};
