import React, { useState, useEffect, useCallback } from 'react';
import { Responsive, WidthProvider } from 'react-grid-layout';
import WidgetCard from './WidgetCard';
import DashboardModal from './DashboardModal';
import api from '../services/api';
import 'react-grid-layout/css/styles.css';
import 'react-resizable/css/styles.css';
import './Dashboard.css';
import { debounce } from 'lodash';

const ResponsiveGridLayout = WidthProvider(Responsive);

const Dashboard = () => {
  const [currentDashboardId, setCurrentDashboardId] = useState("");
  const [widgets, setWidgets] = useState([]);
  const [refreshTime, setRefreshTime] = useState(0);
  const [refreshInterval, setRefreshInterval] = useState(null);
  const [dashboards, setDashboards] = useState([]);
  const [modalState, setModalState] = useState({
    isOpen: false,
    mode: 'create', // 'create' or 'edit'
    dashboard: null
  });
  const [deleteConfirmOpen, setDeleteConfirmOpen] = useState(false);
  const [layoutLoaded, setLayoutLoaded] = useState(false);
  const [isViewMode, setIsViewMode] = useState(false);


  useEffect(() => {
      const initialize = async () => {
          const dbs = await loadDashboards(); // Assuming loadDashboards now returns the data
          if (dbs && dbs.length > 0) {
              const activeDashboardId = dbs.find(d => d.active)?.id || dbs[0].id;
              setCurrentDashboardId(activeDashboardId);
          }
      };
      initialize();
  }, []); // Empty dependency array is fine here

  useEffect(() => {
      if (currentDashboardId) {
          loadDashboard(currentDashboardId);
          // Now find the dashboard from the 'dashboards' state which is guaranteed to be loaded
          const currentDb = dashboards.find(d => d.id === parseInt(currentDashboardId));
          setRefreshTime(parseRefreshTime(currentDb?.refresh_time));
          setLayoutLoaded(false); // Reset layout loaded state when dashboard changes
      }
  }, [currentDashboardId, dashboards]); // Add 'dashboards' as a dependency here

  useEffect(() => {
    // Setup auto-refresh
    if (refreshInterval) {
      clearInterval(refreshInterval);
    }

    if (refreshTime > 0) {
      const interval = setInterval(() => {
        loadDashboard(currentDashboardId, false);
      }, refreshTime * 1000);
      setRefreshInterval(interval);

      return () => clearInterval(interval);
    }
  }, [refreshTime, currentDashboardId]);

  const loadDashboard = async (dashboardId, changeActive = true) => {
    try {
      if (changeActive) {
        const data = await api.changeActiveDashboard(dashboardId);
        if(data){
          setWidgets(data);
          setLayoutLoaded(true); // Mark layout as loaded after widgets are set
        }
      }
    } catch (error) {
      console.error('Error loading dashboard:', error);
    }
  };

  const loadDashboards = async() => {
    try {
      const data = await api.getDashboards();
      if(data){
        setDashboards(data);
        return data;
      }
      return [];
    } catch (error) {
      console.error('Error loading dashboard:', error);
      return [];
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

  const saveLayoutsToBackend = async (dashboardId, widgetsPositionalInformation) => {
        try {
            await api.updateWidgetLayouts(dashboardId, widgetsPositionalInformation);
        } catch (error) {
            console.error('Error updating layout:', error);
        }
    };

  const debouncedSaveLayout = useCallback(
        debounce((widgetsPositionalInformation) => {
            saveLayoutsToBackend(currentDashboardId, widgetsPositionalInformation);
        }, 1000), 
        [currentDashboardId] // Recreate the debounced function if the current dashboard changes
    );

    // 3. Cleanup effect for the debounced function
    useEffect(() => {
        return () => {
            // Cancel any pending debounced call when the component unmounts
            // or when currentDashboardId changes and debouncedSaveLayout is recreated
            debouncedSaveLayout.cancel();
        };
    }, [debouncedSaveLayout]);

    useEffect(() => {
        return () => {
            // Cancel any pending debounced call when the component unmounts
            // or when currentDashboardId changes and debouncedSaveLayout is recreated
            debouncedSaveLayout.cancel();
        };
    }, [debouncedSaveLayout]);

  const handleDashboardChange = (e) => {
    const newDashboardId = parseInt(e.target.value);
    setCurrentDashboardId(newDashboardId);
  };

  const handleCreateDashboard = () => {
    setModalState({
      isOpen: true,
      mode: 'create',
      dashboard: null
    });
  };

  const handleUpdateDashboard = () => {
    const currentDashboard = dashboards.find(d => d.id === parseInt(currentDashboardId));
    if (currentDashboard) {
      setModalState({
        isOpen: true,
        mode: 'edit',
        dashboard: currentDashboard
      });
    }
  };

  const handleDeleteDashboard = () => {
    if (dashboards.length === 1) {
      alert('Cannot delete the last dashboard');
      return;
    }
    setDeleteConfirmOpen(true);
  };

  const confirmDelete = async () => {
    try {
      await api.deleteDashboard(currentDashboardId);
      
      // Refresh dashboards list
      const updatedDashboards = await api.getDashboards();
      setDashboards(updatedDashboards);
      
      // Set to first available dashboard
      const newActiveDashboard = updatedDashboards[0];
      if (newActiveDashboard) {
        setCurrentDashboardId(newActiveDashboard.id);
      }
      
      setDeleteConfirmOpen(false);
    } catch (error) {
      console.error('Error deleting dashboard:', error);
      alert('Failed to delete dashboard');
    }
  };

  const handleModalSubmit = async (formData) => {
    try {
      if (modalState.mode === 'create') {
        // Create new dashboard
        const newDashboard = await api.createDashboard(formData);
        
        // Refresh dashboards list
        const updatedDashboards = await api.getDashboards();
        setDashboards(updatedDashboards);
        
        // Switch to the new dashboard
        setCurrentDashboardId(newDashboard.id);
      } else if (modalState.mode === 'edit') {
        // Update existing dashboard
        await api.updateDashboard(currentDashboardId, formData);
        
        // Refresh dashboards list
        const updatedDashboards = await api.getDashboards();
        setDashboards(updatedDashboards);
        
        // Update refresh time if it changed
        setRefreshTime(parseRefreshTime(formData.refresh_time));
      }
    } catch (error) {
      console.error('Error saving dashboard:', error);
      alert('Failed to save dashboard');
    }
  };

  const handleAddWidget = async () => {
    try {
      const result = await api.createWidget(
        currentDashboardId, 
        {title: 'New Widget', chart_type: null}  
      );
      console.log(widgets);
      if (result && result.widget) {
        // Add the new widget to the state instead of reloading
        setWidgets(prevWidgets => [...prevWidgets, result.widget]);
      }
    } catch (error) {
      console.error('Error adding widget:', error);
    }
  };

  const handleWidgetUpdate = (updatedWidget) => {
    setWidgets(prevWidgets => 
      prevWidgets.map(w => 
        w.id === updatedWidget.id ? updatedWidget : w
      )
    );
  };

  const handleWidgetDelete = (widgetId) => {
    setWidgets(widgets.filter(w => w.id !== widgetId));
  };

  const handleLayoutChange = async (layout) => {
    // Only save layout changes if the initial layout has been loaded
    // This prevents saving the default layout on initial render
    if (!layoutLoaded) {
      return;
    }

    // Map layout changes to widget layout data
    const widgetsPositionalInformation = layout.map(item => ({
      widget_id: parseInt(item.i),
      x: item.x,
      y: item.y,
      w: item.w,
      h: item.h
    }));
    
    // Update widget state with new layout information
    setWidgets(prevWidgets => 
      prevWidgets.map(widget => {
        const layoutItem = layout.find(item => parseInt(item.i) === widget.id);
        if (layoutItem) {
          return {
            ...widget,
            layout: {
              x: layoutItem.x,
              y: layoutItem.y,
              w: layoutItem.w,
              h: layoutItem.h
            }
          };
        }
        return widget;
      })
    );
    
    debouncedSaveLayout(widgetsPositionalInformation);
  };

  const getLayout = () => {
    return widgets.map((widget, index) => {
      const layout = widget.layout || '{}';
      
      // Check if widget has saved layout data
      if (layout && typeof layout === 'object') {
        return {
          i: widget.id.toString(),
          x: layout.x ?? (index % 4) * 3,
          y: layout.y ?? Math.floor(index / 4) * 4,
          w: layout.w ?? 4,
          h: layout.h ?? 4,
          minW: 3,
          minH: 3
        };
      }
      
      // Fallback to default layout
      return {
        i: widget.id.toString(),
        x: (index % 4) * 3,
        y: Math.floor(index / 4) * 4,
        w: 4,
        h: 4,
        minW: 3,
        minH: 3
      };
    });
  };


  // const currentDashboard = dashboards.find(d => d.id === parseInt(currentDashboardId));
  const visibleWidgets = widgets.filter(w => w.dashboard_id === parseInt(currentDashboardId));


  return (
    <div className="dashboard-container">
      <div className="dashboard-header">
        <div className="header-left">
          <div className="dashboard-select-container">
            <label htmlFor="dashboard-selector" className="dashboard-select-label">
              Dashboard:
            </label>
            <select 
              id="dashboard-selector"
              value={currentDashboardId} 
              onChange={handleDashboardChange}
              className="dashboard-select"
            >
              {dashboards.map(dashboard => (
                <option key={dashboard.id} value={dashboard.id}>
                  {dashboard.name}
                </option>
              ))}
            </select>
          </div>
        </div>
        
        <div className="dashboard-actions">
          <button 
            className={`dashboard-action-btn ${isViewMode ? 'edit-mode-btn' : 'view-mode-btn'}`}
            onClick={() => setIsViewMode(!isViewMode)}
            title={isViewMode ? "Switch to Edit Mode" : "Switch to View Mode"}
          >
            <span>{isViewMode ? 'Edit Mode' : 'View Mode'}</span>
          </button>
          
          <button 
            className="dashboard-action-btn create-btn"
            onClick={handleCreateDashboard}
            title="Create New Dashboard"
            disabled={isViewMode}
          >            
            <span>New</span>
          </button>
          
          <button 
            className="dashboard-action-btn update-btn"
            onClick={handleUpdateDashboard}
            title="Edit Dashboard"
            disabled={isViewMode}
          >
            <span>Edit</span>
          </button>
          
          <button 
            className="dashboard-action-btn delete-btn"
            onClick={handleDeleteDashboard}
            title="Delete Dashboard"
            disabled={isViewMode}
          >
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
          draggableHandle=".widget-drag-handle"
          compactType="vertical"
          isDraggable={!isViewMode}
          isResizable={!isViewMode}
          static={isViewMode}
          
        >
          {visibleWidgets.map(widget => (
            <div key={widget.id.toString()}>
              <WidgetCard
                widget={widget}
                onWidgetUpdate={handleWidgetUpdate}
                onDelete={handleWidgetDelete}
                isViewMode={isViewMode}
              />
            </div>
          ))}
        </ResponsiveGridLayout>
      </div>

      <button 
        className="add-widget-fab"
        onClick={handleAddWidget}
        title="Add Widget"
        disabled={isViewMode}
        style={{ display: isViewMode ? 'none' : 'flex' }}
      >
      </button>

      <DashboardModal
        isOpen={modalState.isOpen}
        onClose={() => setModalState({ ...modalState, isOpen: false })}
        onSubmit={handleModalSubmit}
        dashboard={modalState.dashboard}
        mode={modalState.mode}
      />

      {deleteConfirmOpen && (
        <div className="modal-overlay" onClick={() => setDeleteConfirmOpen(false)}>
          <div className="modal-content delete-confirm" onClick={(e) => e.stopPropagation()}>
            <div className="modal-header">
              <h2>Confirm Delete</h2>
              <button className="modal-close-btn" onClick={() => setDeleteConfirmOpen(false)}>
              </button>
            </div>
            <div className="modal-form">
              <p>Are you sure you want to delete this dashboard? This action cannot be undone.</p>
              <div className="modal-actions">
                <button className="btn-secondary" onClick={() => setDeleteConfirmOpen(false)}>
                  Cancel
                </button>
                <button className="btn-danger" onClick={confirmDelete}>
                  Delete
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default Dashboard;
