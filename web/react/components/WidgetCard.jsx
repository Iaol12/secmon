import React, { useState, useEffect } from 'react';
import BarChart from './charts/BarChart';
import PieChart from './charts/PieChart';
import DataTable from './charts/DataTable';
import WidgetSettings from './WidgetSettings';
import api from '../services/api';
import './WidgetCard.css';

const WidgetCard = ({ 
  component, 
  onUpdate, 
  onDelete,
  filters,
  tableColumns 
}) => {
  const [isLoading, setIsLoading] = useState(false);
  const [contentData, setContentData] = useState(null);
  const [showSettings, setShowSettings] = useState(false);
  const [showContentModal, setShowContentModal] = useState(false);
  const [currentPage, setCurrentPage] = useState(1);

  const config = JSON.parse(component.config || '{}');
  const hasContent = component.filter_id !== null;

  useEffect(() => {
    if (hasContent) {
      loadContent();
    }
  }, [component.id, hasContent, currentPage]);

  const loadContent = async () => {
    setIsLoading(true);
    try {
      const data = await api.getComponentContent(component.id, currentPage);
      setContentData(data);
    } catch (error) {
      console.error('Error loading content:', error);
    } finally {
      setIsLoading(false);
    }
  };

  const handleSaveSettings = async (newConfig) => {
    try {
      await api.updateComponent(component.id, newConfig);
      onUpdate();
      setShowSettings(false);
    } catch (error) {
      console.error('Error saving settings:', error);
    }
  };

  const handleDelete = async () => {
    if (window.confirm('Are you sure you want to delete this widget?')) {
      try {
        await api.deleteComponent(component.id);
        onDelete(component.id);
      } catch (error) {
        console.error('Error deleting component:', error);
      }
    }
  };

  const handleSaveContent = async (contentSettings) => {
    try {
      await api.updateComponentSettings(contentSettings);
      await loadContent();
      setShowContentModal(false);
      onUpdate();
    } catch (error) {
      console.error('Error saving content:', error);
    }
  };

  const handleDeleteContent = async () => {
    try {
      await api.deleteComponentSettings({ componentId: component.id });
      setContentData(null);
      setShowContentModal(false);
      onUpdate();
    } catch (error) {
      console.error('Error deleting content:', error);
    }
  };

  const renderContent = () => {
    if (!hasContent) {
      return (
        <div className="widget-empty-state">
          <button 
            className="add-content-btn"
            onClick={() => setShowContentModal(true)}
          >
            <i className="material-icons">add_circle_outline</i>
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

    const { contentTypeId, data, html } = contentData;

    switch (contentTypeId) {
      case 'barChart':
        return <BarChart data={JSON.parse(data)} />;
      case 'pieChart':
        return <PieChart data={JSON.parse(data)} />;
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
        <div className="widget-header">
          <h3 className="widget-title">{config.name || 'New Component'}</h3>
          <div className="widget-actions">
            {hasContent && (
              <button 
                className="widget-action-btn edit"
                onClick={() => setShowContentModal(true)}
                title="Edit content"
              >
                <i className="material-icons">edit</i>
              </button>
            )}
            <button 
              className="widget-action-btn settings"
              onClick={() => setShowSettings(true)}
              title="Settings"
            >
              <i className="material-icons">settings</i>
            </button>
          </div>
        </div>
        <div className="widget-content">
          {renderContent()}
        </div>
      </div>

      {showSettings && (
        <WidgetSettings
          component={component}
          config={config}
          onSave={handleSaveSettings}
          onDelete={handleDelete}
          onClose={() => setShowSettings(false)}
        />
      )}

      {showContentModal && (
        <ContentModal
          component={component}
          filters={filters}
          tableColumns={tableColumns}
          hasContent={hasContent}
          onSave={handleSaveContent}
          onDelete={handleDeleteContent}
          onClose={() => setShowContentModal(false)}
        />
      )}
    </>
  );
};

// Content configuration modal
const ContentModal = ({ 
  component, 
  filters, 
  tableColumns,
  hasContent,
  onSave, 
  onDelete,
  onClose 
}) => {
  const [filterId, setFilterId] = useState(component.filter_id || (filters[0]?.id || ''));
  const [contentType, setContentType] = useState(component.data_type || 'table');
  const [dataParam, setDataParam] = useState(component.data_param || 'id,datetime,device_host_name,application_protocol');

  const handleSubmit = (e) => {
    e.preventDefault();
    onSave({
      componentId: component.id,
      filterId,
      contentTypeId: contentType,
      dataTypeParameter: dataParam
    });
  };

  const contentTypes = [
    { value: 'table', label: 'Table' },
    { value: 'barChart', label: 'Bar chart' },
    { value: 'pieChart', label: 'Pie chart' }
  ];

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-content" onClick={(e) => e.stopPropagation()}>
        <div className="modal-header">
          <h4>Content Settings</h4>
          <button className="modal-close" onClick={onClose}>
            <i className="material-icons">close</i>
          </button>
        </div>
        
        <form onSubmit={handleSubmit}>
          <div className="modal-body">
            <div className="form-group">
              <label>Filter</label>
              <select 
                value={filterId} 
                onChange={(e) => setFilterId(e.target.value)}
                required
              >
                {filters.map(filter => (
                  <option key={filter.id} value={filter.id}>
                    {filter.name}
                  </option>
                ))}
              </select>
            </div>

            <div className="form-group">
              <label>Content Type</label>
              <select 
                value={contentType} 
                onChange={(e) => setContentType(e.target.value)}
                required
              >
                {contentTypes.map(type => (
                  <option key={type.value} value={type.value}>
                    {type.label}
                  </option>
                ))}
              </select>
            </div>

            <div className="form-group">
              <label>
                {contentType === 'table' ? 'Table Columns (comma-separated)' :
                 contentType === 'barChart' ? 'Time Range (e.g., 1D, 1W, 1M)' :
                 'Column to Chart'}
              </label>
              <input
                type="text"
                value={dataParam}
                onChange={(e) => setDataParam(e.target.value)}
                placeholder={
                  contentType === 'table' ? 'id,datetime,device_host_name' :
                  contentType === 'barChart' ? '1D' :
                  'column_name'
                }
              />
            </div>
          </div>

          <div className="modal-footer">
            {hasContent && (
              <button 
                type="button" 
                className="btn btn-danger"
                onClick={onDelete}
              >
                Delete Content
              </button>
            )}
            <div className="modal-footer-right">
              <button type="submit" className="btn btn-primary">
                Save
              </button>
              <button type="button" className="btn btn-secondary" onClick={onClose}>
                Cancel
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  );
};

export default WidgetCard;
