import React from 'react';
import { useParameter, useStorybookApi } from 'storybook/manager-api';

interface Slot {
  name: string;
  description: string;
  required: boolean;
  type: string;
  example?: string;
}

export const SlotsPanel = () => {
  const [slots, setSlots] = React.useState<Slot[]>([]);
  const [loading, setLoading] = React.useState<boolean>(true);
  const api = useStorybookApi();
  const slotsParameter = useParameter('slots', []);

  // Use the proper Storybook hooks to get slots data
  React.useEffect(() => {
    if (Array.isArray(slotsParameter) && slotsParameter.length > 0) {
      setSlots(slotsParameter);
      setLoading(false);
    } else {
      // Try to get current story data from API
      try {
        const currentStory = api.getCurrentStoryData?.();
        if (currentStory?.parameters?.slots) {
          setSlots(currentStory.parameters.slots);
        } else {
          setSlots([]);
        }
        setLoading(false);
      } catch (error) {
        setSlots([]);
        setLoading(false);
      }
    }
  }, [slotsParameter, api]);


  if (loading) {
    return React.createElement('div', { 
      style: { 
        padding: '16px', 
        textAlign: 'center', 
        color: '#666' 
      } 
    }, 'Loading slots...');
  }

  if (slots.length === 0) {
    return React.createElement('div', { 
      style: { 
        padding: '16px', 
        textAlign: 'center', 
        color: '#666',
        fontStyle: 'italic'
      } 
    }, 'No slot information available for this component');
  }

  return React.createElement('div', { style: { padding: '16px' } },
    React.createElement('h2', { 
      style: { 
        margin: '0 0 16px 0', 
        color: '#333',
        fontSize: '16px',
        fontWeight: 'bold'
      } 
    }, 'Twig Slots'),
    
    React.createElement('div', { style: { marginBottom: '16px' } },
      
      ...slots.map((slot: Slot, index: number) =>
        React.createElement('div', { 
          key: index,
          style: { 
            marginBottom: '16px', 
            padding: '16px', 
            border: '1px solid #e0e0e0', 
            borderRadius: '8px',
            backgroundColor: '#fafafa'
          } 
        },
          React.createElement('h3', { 
            style: { 
              margin: '0 0 8px 0',
              color: '#007acc',
              fontSize: '16px',
              fontWeight: 'bold'
            } 
          }, slot.name),
          
          React.createElement('p', { 
            style: { 
              margin: '0', 
              fontSize: '14px', 
              color: '#666',
              lineHeight: '1.5'
            } 
          }, slot.description)
        )
      )
    )
  );
};
