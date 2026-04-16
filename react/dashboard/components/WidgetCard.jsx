import React, { useState, useEffect, useRef } from 'react';
import BarChart from './charts/BarChart';
import PieChart from './charts/PieChart';
import LineChart from './charts/LineChart';
import EventTable from './charts/EventTable';
import GeoMap from './charts/GeoMap';
import WidgetSettings from './WidgetSettings';
import api from '../services/api';
import './WidgetCard.css';
import ReactDOM from 'react-dom';

const WidgetCard = ({ 
  widget, 
  onWidgetUpdate, 
  onDelete,
  isViewMode = false
}) => {
  const [isLoading, setIsLoading] = useState(false);
  const [contentData, setContentData] = useState(null);
  const [showSettings, setShowSettings] = useState(false);
  const [currentPage, setCurrentPage] = useState(1);
  const [pollInterval, setPollInterval] = useState(null);
  const lastFetchTimestampRef = useRef(null);

  const config = JSON.parse(widget.config || '{}');
  const hasContent = widget.chart_type !== null && widget.chart_type !== undefined && widget.chart_type !== '';

  // Reset page to 1 when chart type or config changes
  useEffect(() => {
    setCurrentPage(1);
  }, [widget.chart_type, widget.config]);

  // Initial load and setup polling
  useEffect(() => {
    if (hasContent) {
      // Initial load without timestamp
      loadContent(true);
      // Setup 5-second polling
      const interval = setInterval(() => {
        loadContent(false);
      }, 5000);
      setPollInterval(interval);
      
      return () => {
        if (interval) {
          clearInterval(interval);
        }
      };
    } else {
      // Clear polling if no content
      if (pollInterval) {
        clearInterval(pollInterval);
        setPollInterval(null);
      }
    }
  }, [widget.id, widget.filter_id, widget.timeframe, widget.chart_type, widget.config]);

  // Merge delta data based on chart type
  const mergeData = (existingData, newDeltaData, chartType) => {
    if (!newDeltaData || newDeltaData.length === 0) {
      return existingData;
    }

    switch (chartType) {
      case 'pieChart':
      case 'barChart':
        // For pie/bar charts, merge by label/field
        return mergeChartData(existingData, newDeltaData);
      
      case 'lineChart':
        // For line charts, merge by x value (timestamp)
        return mergeLineChartData(existingData, newDeltaData);
      
      case 'table':
        // For tables, prepend new rows (most recent first)
        return [...newDeltaData, ...existingData];
      
      case 'geoMap':
        // For geo maps, merge by code
        return mergeGeoData(existingData, newDeltaData);
      
      default:
        return existingData;
    }
  };

  // Merge pie/bar chart data by aggregating counts
  const mergeChartData = (existing, delta) => {
    const merged = [...existing];
    
    delta.forEach(newItem => {
      const existingItem = merged.find(item => 
        (item.label === newItem.label || item.x === newItem.x || item.name === newItem.name)
      );
      
      if (existingItem) {
        // Add to existing count
        existingItem.count = (existingItem.count || 0) + (newItem.count || 0);
        existingItem.y = (existingItem.y || 0) + (newItem.y || 0);
      } else {
        // New data point
        merged.push(newItem);
      }
    });
    
    return merged;
  };

  // Merge line chart data by time bucket
  const mergeLineChartData = (existing, delta) => {
    const merged = [...existing];
    
    delta.forEach(newItem => {
      const existingItem = merged.find(item => item.x === newItem.x);
      
      if (existingItem) {
        // Add to existing y value
        existingItem.y = (existingItem.y || 0) + (newItem.y || 0);
      } else {
        // New time bucket
        merged.push(newItem);
      }
    });
    
    // Sort by x to maintain chronological order
    return merged.sort((a, b) => a.x.localeCompare(b.x));
  };

  // Merge geo map data by country code
  const mergeGeoData = (existing, delta) => {
    const merged = [...existing];
    
    delta.forEach(newItem => {
      const existingItem = merged.find(item => item.code === newItem.code);
      
      if (existingItem) {
        // Add to existing count
        existingItem.count = (existingItem.count || 0) + (newItem.count || 0);
      } else {
        // New country
        merged.push(newItem);
      }
    });
    
    // Sort by count descending
    return merged.sort((a, b) => b.count - a.count);
  };

  const loadContent = async (isInitial = false) => {
    if (!isInitial) {
      // Only set loading once for the first load
      setIsLoading(false);
    } else {
      setIsLoading(true);
    }

    try {
      // For table charts, use pagination. For others, send only delta timestamp
      const pageParam = widget.chart_type === 'table' ? Number(currentPage) || 1 : null;
      const timestamp = !isInitial && lastFetchTimestampRef.current ? lastFetchTimestampRef.current : null;
      
      const data = await api.getWidgetContent(widget.id, pageParam, timestamp);
      
      if (data) {
        // Store the response timestamp for next poll
        if (data.timestamp) {
          lastFetchTimestampRef.current = data.timestamp;
        }

        // For initial load, just set the data
        if (isInitial) {
          setContentData(data);
        } else {
          // For subsequent polls, merge data (unless it's a table, which gets fresh data)
          if (widget.chart_type === 'table') {
            // Tables should reset to page 1 and show fresh data
            setContentData(data);
          } else {
            // Merge delta data for other chart types
            setContentData(prevData => {
              if (!prevData) return data;
              
              return {
                ...data,
                data: mergeData(prevData.data || [], data.data || [], widget.chart_type)
              };
            });
          }
        }
      }
    } catch (error) {
      console.error('Error loading content:', error);
    } finally {
      if (isInitial) {
        setIsLoading(false);
      }
    }
  };

  const handleSaveSettings = async (newConfig) => {
    try {
      const result = await api.updateWidgetSettings(newConfig);
      if (result.success && result.widget) {
        onWidgetUpdate(result.widget);
        setShowSettings(false);
        // Reset timestamp on settings change
        lastFetchTimestampRef.current = null;
      }
    } catch (error) {
      console.error('Error saving settings:', error);
    }
  };

  const handleDelete = async () => {
      try {
        await api.deleteWidget(widget.id);
        onDelete(widget.id);
      } catch (error) {
        console.error('Error deleting widget:', error);
      }
  };

  const renderContent = () => {
    if (!hasContent) {
      if (isViewMode) {
        return (
          <div className="widget-empty-state">
            {/* <p style={{ color: '#999', fontSize: '14px' }}>No content</p> */}
          </div>
        );
      }
      return (
        <div className="widget-empty-state">
          <button 
            className="add-content-btn"
            onClick={() => setShowSettings(true)}
          >
            Add content
          </button>
        </div>
      );
    }

    if (isLoading) {
      return (
        <div className="widget-loader">
          <div className="spinner"></div>
          <p>Loading...</p>
        </div>
      );
    }

    if (!contentData) {
      return <div className="widget-error">Failed to load content</div>;
    }

    const chartType = widget.chart_type;

    switch (chartType) {
      case 'barChart':
        return <BarChart data={contentData.data} />;
      case 'pieChart':
        return <PieChart data={contentData.data} config={config} />;
      case 'lineChart':
        return <LineChart data={contentData.data} />;
      case 'table':
        return (
          <EventTable 
            data={contentData.data}
            columns={contentData.columns}
            pagination={contentData.pagination}
            onPageChange={(page) => setCurrentPage(Number(page))}
          />
        );
      case 'geoMap':
        return <GeoMap data={contentData.data} />;
      default:
        return <div>Unknown content type</div>;
    }
  };

  return (
    <>
      <div className="widget-card">
        <div className={`widget-header ${isViewMode ? 'view-mode' : ''}`}>
          <div className="widget-drag-handle">
            <h3 className="widget-title">{widget.title || 'New Widget'}</h3>
          </div>
          {!isViewMode && (
            <div className="widget-actions">
              <button 
                className="widget-action-btn settings"
                onClick={() => setShowSettings(true)}
                title="Settings"
              >
                🔧
              </button>
            </div>
          )}
        </div>
        <div className="widget-content">
          {renderContent()}
        </div>
      </div>

      {showSettings && (
        ReactDOM.createPortal(
        <WidgetSettings
          widget={widget}
          config={config}
          onSave={handleSaveSettings}
          onDelete={handleDelete}
          onClose={() => setShowSettings(false)}
        />, document.getElementById('react-dashboard-root'))
      )}
    </>
  );
};

export default WidgetCard;
