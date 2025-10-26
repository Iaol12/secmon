import React, { useState, useEffect } from 'react';
import { Responsive, WidthProvider } from 'react-grid-layout';
import WidgetCard from './WidgetCard';
import api from '../services/api';
import 'react-grid-layout/css/styles.css';
import 'react-resizable/css/styles.css';
import './Dashboard.css';

const ResponsiveGridLayout = WidthProvider(Responsive);

const Dashboard = ({ views, activeViewId, filters, tableColumns }) => {
  const [currentViewId, setCurrentViewId] = useState(activeViewId);
  const [components, setComponents] = useState([]);
  const [refreshTime, setRefreshTime] = useState(0);
  const [refreshInterval, setRefreshInterval] = useState(null);

  useEffect(() => {
    loadView(currentViewId);
    loadRefreshTimes();
  }, [currentViewId]);

  useEffect(() => {
    // Setup auto-refresh
    if (refreshInterval) {
      clearInterval(refreshInterval);
    }

    if (refreshTime > 0) {
      const interval = setInterval(() => {
        loadView(currentViewId, false);
      }, refreshTime * 1000);
      setRefreshInterval(interval);

      return () => clearInterval(interval);
    }
  }, [refreshTime, currentViewId]);

  const loadView = async (viewId, changeActive = true) => {
    try {
      if (changeActive) {
        await api.changeView(viewId);
      }
      
      // Get components for the view from the views prop
      const view = views.find(v => v.id === parseInt(viewId));
      if (view && view.components) {
        setComponents(view.components);
      } else {
        setComponents([]);
      }
    } catch (error) {
      console.error('Error loading view:', error);
    }
  };

  const loadRefreshTimes = async () => {
    try {
      const times = await api.getRefreshTimes();
      const time = times[currentViewId];
      setRefreshTime(parseRefreshTime(time));
    } catch (error) {
      console.error('Error loading refresh times:', error);
    }
  };

  const parseRefreshTime = (refreshString) => {
    if (!refreshString || refreshString === '0') return 0;
    
    const unit = refreshString.slice(-1);
    const value = parseInt(refreshString.slice(0, -1));
    
    const multipliers = {
      'S': 1,
      'm': 60,
      'H': 3600,
      'D': 86400,
      'W': 604800,
      'M': 2592000,
      'Y': 31536000
    };
    
    return value * (multipliers[unit] || 1);
  };

  const handleViewChange = (e) => {
    const newViewId = parseInt(e.target.value);
    setCurrentViewId(newViewId);
    
    // Dispatch event for external listeners (PHP buttons)
    window.dispatchEvent(new CustomEvent('dashboardViewChanged', {
      detail: { viewId: newViewId }
    }));
  };

  const handleAddWidget = async () => {
    try {
      const newConfig = {
        name: 'New Component',
        width: ''
      };
      
      const result = await api.createComponent(
        currentViewId, 
        newConfig, 
        components.length
      );
      
      if (result) {
        loadView(currentViewId, false);
      }
    } catch (error) {
      console.error('Error adding widget:', error);
    }
  };

  const handleWidgetUpdate = () => {
    loadView(currentViewId, false);
  };

  const handleWidgetDelete = (componentId) => {
    setComponents(components.filter(c => c.id !== componentId));
  };

  const handleLayoutChange = async (layout) => {
    // Map layout changes back to component order
    const order = layout.map((item, index) => ({
      id: item.i,
      order: index
    }));

    try {
      await api.updateComponentOrder(currentViewId, order);
    } catch (error) {
      console.error('Error updating layout:', error);
    }
  };

  const getLayout = () => {
    return components.map((component, index) => {
      const config = JSON.parse(component.config || '{}');
      const width = getWidthFromConfig(config.width);
      
      return {
        i: component.id.toString(),
        x: (index % 4) * 3,
        y: Math.floor(index / 4) * 4,
        w: width,
        h: 4,
        minW: 3,
        minH: 3
      };
    });
  };

  const getWidthFromConfig = (widthClass) => {
    const widthMap = {
      '': 3,      // 25%
      'width2': 6,  // 50%
      'width3': 9,  // 75%
      'width4': 12  // 100%
    };
    return widthMap[widthClass] || 3;
  };

  const currentView = views.find(v => v.id === parseInt(currentViewId));
  const visibleComponents = components.filter(c => c.view_id === parseInt(currentViewId));

  const handleCreateView = () => {
    // Get URLs from window.dashboardConfig
    const config = window.dashboardConfig || {};
    const createUrl = config.urls?.createView || `/view/create`;
    window.location.href = createUrl;
  };

  const handleUpdateView = () => {
    const config = window.dashboardConfig || {};
    const updateUrl = config.urls?.updateView || `/view/update?id=${currentViewId}`;
    window.location.href = updateUrl;
  };

  const handleDeleteView = () => {
    if (!confirm('Are you sure you want to delete this dashboard?')) {
      return;
    }
    
    const config = window.dashboardConfig || {};
    const deleteUrl = config.urls?.deleteView || `/view/delete?id=${currentViewId}`;
    
    // Create a form to submit DELETE request
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = deleteUrl;
    
    // Add CSRF token if available
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (csrfToken) {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = '_csrf';
      input.value = csrfToken;
      form.appendChild(input);
    }
    
    document.body.appendChild(form);
    form.submit();
  };

  return (
    <div className="dashboard-container">
      <div className="dashboard-header">
        <div className="dashboard-select-container">
          <select 
            value={currentViewId} 
            onChange={handleViewChange}
            className="dashboard-select"
          >
            {views.map(view => (
              <option key={view.id} value={view.id}>
                {view.name}
              </option>
            ))}
          </select>
        </div>
        
        <div className="dashboard-actions">
          <button 
            className="dashboard-action-btn create-btn"
            onClick={handleCreateView}
            title="Create View"
          >
            <i className="material-icons">add_to_queue</i>
            <span>Create View</span>
          </button>
          
          <button 
            className="dashboard-action-btn update-btn"
            onClick={handleUpdateView}
            title="Update"
          >
            <i className="material-icons">edit</i>
            <span>Update</span>
          </button>
          
          <button 
            className="dashboard-action-btn delete-btn"
            onClick={handleDeleteView}
            title="Delete"
          >
            <i className="material-icons">delete</i>
            <span>Delete</span>
          </button>
        </div>
      </div>

      <div className="dashboard-grid">
        <ResponsiveGridLayout
          className="layout"
          layouts={{ lg: getLayout() }}
          breakpoints={{ lg: 1200, md: 996, sm: 768, xs: 480, xxs: 0 }}
          cols={{ lg: 12, md: 10, sm: 6, xs: 4, xxs: 2 }}
          rowHeight={80}
          onLayoutChange={handleLayoutChange}
          draggableHandle=".widget-header"
          compactType="vertical"
        >
          {visibleComponents.map(component => (
            <div key={component.id.toString()}>
              <WidgetCard
                component={component}
                filters={filters}
                tableColumns={tableColumns}
                onUpdate={handleWidgetUpdate}
                onDelete={handleWidgetDelete}
              />
            </div>
          ))}
        </ResponsiveGridLayout>
      </div>

      <button 
        className="add-widget-fab"
        onClick={handleAddWidget}
        title="Add Widget"
      >
        <i className="material-icons">add</i>
      </button>
    </div>
  );
};

export default Dashboard;
