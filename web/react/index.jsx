import React from 'react';
import ReactDOM from 'react-dom/client';
import Dashboard from './components/Dashboard';
import './index.css';

// Initialize the React app
const initDashboard = () => {
  const container = document.getElementById('react-dashboard-root');
  
  if (!container) {
    console.error('Dashboard container not found!');
    return;
  }

  // Get configuration from window object (passed from PHP)
  const config = window.dashboardConfig || {};
  console.log(config)
  const {
    views = [],
    activeViewId = null,
    filters = [],
    tableColumns = {}
  } = config;

  const root = ReactDOM.createRoot(container);
  root.render(
    <React.StrictMode>
      
      <Dashboard
        views={views}
        activeViewId={activeViewId}
        filters={filters}
        tableColumns={tableColumns}
      />
    </React.StrictMode>
  );
};

// Wait for DOM to be ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initDashboard);
} else {
  initDashboard();
}

export default initDashboard;
