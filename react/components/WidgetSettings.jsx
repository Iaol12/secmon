import React, { useState } from 'react';

const WidgetSettings = ({ component, config, onSave, onDelete, onClose }) => {
  const [name, setName] = useState(config.name || '');
  const [width, setWidth] = useState(config.width || '');

  const handleSubmit = (e) => {
    e.preventDefault();
    onSave({ name, width });
  };

  const widthOptions = [
    { value: '', label: '25%' },
    { value: 'width2', label: '50%' },
    { value: 'width3', label: '75%' },
    { value: 'width4', label: '100%' }
  ];

  return (
    <div className="modal-overlay" onClick={onClose}>
      <div className="modal-content" onClick={(e) => e.stopPropagation()}>
        <div className="modal-header">
          <h4>{name || 'Widget'} - Options</h4>
          <button className="modal-close" onClick={onClose}>
            <i className="material-icons">close</i>
          </button>
        </div>
        
        <form onSubmit={handleSubmit}>
          <div className="modal-body">
            <div className="form-group">
              <label htmlFor="widgetName">Name</label>
              <input
                id="widgetName"
                type="text"
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="Widget name"
                required
              />
            </div>

            <div className="form-group">
              <label htmlFor="widgetWidth">Width</label>
              <select 
                id="widgetWidth"
                value={width} 
                onChange={(e) => setWidth(e.target.value)}
              >
                {widthOptions.map(option => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div className="modal-footer">
            <button 
              type="button" 
              className="btn btn-danger"
              onClick={onDelete}
            >
              Delete Widget
            </button>
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

export default WidgetSettings;
