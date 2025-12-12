import React, { useState, useEffect } from 'react';
import BarChart from './charts/BarChart';
import PieChart from './charts/PieChart';
import LineChart from './charts/LineChart';
import EventTable from './charts/EventTable';
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

  // Reset page to 1 when chart type or config changes
  useEffect(() => {
    setCurrentPage(1);
  }, [widget.chart_type, widget.config]);

  useEffect(() => {
    if (hasContent) {
      loadContent();
    }
  }, [widget.id, widget.filter_id, widget.timeframe, widget.chart_type, widget.config, currentPage]);

  const loadContent = async () => {
    setIsLoading(true);
    try {
      // Only send pagination for table charts
      const pageParam = widget.chart_type === 'table' ? Number(currentPage) || 1 : null;
      const data = await api.getWidgetContent(widget.id, pageParam);
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
