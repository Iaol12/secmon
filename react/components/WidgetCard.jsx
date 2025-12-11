import React, { useState, useEffect } from 'react';
import BarChart from './charts/BarChart';
import PieChart from './charts/PieChart';
import LineChart from './charts/LineChart';
import DataTable from './charts/DataTable';
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

  const config = JSON.parse(widget.config || '{}');
  const hasContent = widget.chart_type !== null && widget.chart_type !== undefined && widget.chart_type !== '';

  useEffect(() => {
    if (hasContent) {
      loadContent();
    }
  }, [widget.id, widget.filter_id, widget.chart_type, widget.config, currentPage]);

  const loadContent = async () => {
    setIsLoading(true);
    try {
      const data = await api.getWidgetContent(
        widget.id, 
        widget.filter_id, 
        widget.chart_type || 'table',
        currentPage
      );
      setContentData(data);
    } catch (error) {
      console.error('Error loading content:', error);
    } finally {
      setIsLoading(false);
    }
  };

  const handleSaveSettings = async (newConfig) => {
    try {
      const result = await api.updateWidgetSettings(newConfig);
      if (result.success && result.widget) {
        // Update the widget in parent state
        onWidgetUpdate(result.widget);
        setShowSettings(false);
      }
    } catch (error) {
      console.error('Error saving settings:', error);
    }
  };

  const handleDelete = async () => {
    if (window.confirm('Are you sure you want to delete this widget?')) {
      try {
        await api.deleteWidget(widget.id);
        onDelete(widget.id);
      } catch (error) {
        console.error('Error deleting widget:', error);
      }
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

    const { data, html } = contentData;
    const chartType = widget.chart_type;
    const parsedData = typeof data === 'string' ? JSON.parse(data) : data;

    switch (chartType) {
      case 'barChart':
        return <BarChart data={parsedData} />;
      case 'pieChart':
        return <PieChart data={parsedData} config={config} />;
      case 'lineChart':
        return <LineChart data={parsedData} />;
      case 'table':
        return (
          <DataTable 
            html={html}
            onPageChange={(page) => setCurrentPage(page)}
          />
        );
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
