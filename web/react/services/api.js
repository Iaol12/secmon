import axios from 'axios';

const API_BASE_URL = window.location.origin + '/secmon/web';

class DashboardAPI {
  constructor() {
    this.urls = window.dashboardConfig?.urls || {};
  }

  // Dashboard operations
  async changeView(viewId) {
    try {
      const response = await axios.get(this.urls.changeView, {
        params: { viewId }
      });
      return response.data;
    } catch (error) {
      console.error('Error changing view:', error);
      throw error;
    }
  }

  async getRefreshTimes() {
    try {
      const response = await axios.get(this.urls.getRefreshTimes);
      // Axios already parses JSON, no need to parse again
      // If response.data is a string, parse it; otherwise return as is
      if (typeof response.data === 'string') {
        try {
          return JSON.parse(response.data);
        } catch (e) {
          console.warn('Could not parse refresh times as JSON:', response.data);
          return {};
        }
      }
      return response.data || {};
    } catch (error) {
      console.error('Error getting refresh times:', error);
      throw error;
    }
  }

  // Component operations
  async createComponent(viewId, config, order) {
    try {
      const response = await axios.get(this.urls.createComponent, {
        params: {
          viewId,
          config: JSON.stringify(config),
          order
        }
      });
      return response.data;
    } catch (error) {
      console.error('Error creating component:', error);
      throw error;
    }
  }

  async updateComponent(componentId, config) {
    try {
      const response = await axios.get(this.urls.updateComponent, {
        params: {
          componentId,
          config: JSON.stringify(config)
        }
      });
      return response.data;
    } catch (error) {
      console.error('Error updating component:', error);
      throw error;
    }
  }

  async deleteComponent(componentId) {
    try {
      const response = await axios.get(this.urls.deleteComponent, {
        params: { componentId }
      });
      return response.data;
    } catch (error) {
      console.error('Error deleting component:', error);
      throw error;
    }
  }

  async updateComponentOrder(viewId, componentOrder) {
    try {
      const response = await axios.get(this.urls.updateOrder, {
        params: {
          viewId,
          componentOrder: JSON.stringify(componentOrder)
        }
      });
      return response.data;
    } catch (error) {
      console.error('Error updating component order:', error);
      throw error;
    }
  }

  // Content operations
  async updateComponentSettings(data) {
    try {
      const response = await axios.get(this.urls.updateComponentSettings, {
        params: data
      });
      return response.data;
    } catch (error) {
      console.error('Error updating component settings:', error);
      throw error;
    }
  }

  async deleteComponentSettings(data) {
    try {
      const response = await axios.get(this.urls.deleteComponentSettings, {
        params: data
      });
      return response.data;
    } catch (error) {
      console.error('Error deleting component settings:', error);
      throw error;
    }
  }

  async getComponentContent(componentId, pagination = 1) {
    try {
      const response = await axios.get(this.urls.updateComponentContent, {
        params: {
          componentId,
          pagination
        }
      });
      return response.data;
    } catch (error) {
      console.error('Error getting component content:', error);
      throw error;
    }
  }
}

export default new DashboardAPI();
