import axios from 'axios';
import { 
  mockDashboards, 
  mockWidgets, 
  getMockContent 
} from './mockData';

// Using Vite proxy - requests to /api/* will be forwarded to https://localhost:8445/api/*
// This avoids CORS issues during development
const API_BASE_URL = '/api';

class DashboardAPI {
  /**
   * Initializes the Axios client with the base URL and Authorization header.
   */
  constructor() {
    // Set this to true to use mock data instead of real API calls
    this.useMockData = false;
    
    // SWITCH THIS BACK TO DYNAMIC TOKEN RETRIEVAL WHEN DEPLOYING
    this.authToken = window.dashboardConfig?.authToken || null;
    // this.authToken = 'MEsrdn-6wf7bP-nT8mVJ5YCorYGb1D3m'; 
    
    this.apiClient = axios.create({
      baseURL: API_BASE_URL,
      // Ensure data is sent as application/json, which Yii2 should parse correctly
      headers: {
        'Content-Type': 'application/json',
      },
    });

    this.setupAxiosInterceptors();
  }

  /**
   * Sets up the Bearer Token in the request headers for every call.
   */
  setupAxiosInterceptors() {
    this.apiClient.interceptors.request.use(
      (config) => {
        if (this.authToken) {
          // The Yii2 controller uses HttpBearerAuth, so the header must be 'Authorization: Bearer <token>'
          config.headers.Authorization = `Bearer ${this.authToken}`;
        }
        return config;
      },
      (error) => {
        return Promise.reject(error);
      }
    );
  }

  // --- DASHBOARD ENDPOINTS ---

  /**
   * GET /dashboards (actionDashboards)
   * Retrieves all dashboards for the current user.
   */
  async getDashboards() {
    if (this.useMockData) {
      return new Promise((resolve) => {
        setTimeout(() => resolve(mockDashboards), 100);
      });
    }
    
    try {
      // Endpoint is /api/dashboard/dashboards
      const response = await this.apiClient.get('/dashboard/dashboards');
      return response.data;
    } catch (error) {
      console.error('Error getting dashboards:', error);
      throw error;
    }
  }

  async getDashboard(id) {
    try {
      // Endpoint is /api/dashboard/dashboard/{id}
      const response = await this.apiClient.get(`/dashboard/dashboard/${id}`);
      return response.data;
    } catch (error) {
      console.error(`Error getting dashboard ${id}:`, error);
      throw error;
    }
  }

  async createDashboard(data) {
    try {
      // Endpoint is /api/dashboard/create, uses POST and expects body data
      const response = await this.apiClient.post('/dashboard/create', data);
      return response.data;
    } catch (error) {
      console.error('Error creating dashboard:', error);
      throw error;
    }
  }

  async updateDashboard(id, data) {
    try {
      // Endpoint is /api/dashboard/update/{id}, uses PUT/PATCH and expects body data
      const response = await this.apiClient.put(`/dashboard/${id}`, data);
      return response.data;
    } catch (error) {
      console.error(`Error updating dashboard ${id}:`, error);
      throw error;
    }
  }

  async deleteDashboard(id) {
    try {
      // Endpoint is /api/dashboard/delete/{id}, uses DELETE
      const response = await this.apiClient.delete(`/dashboard/${id}`);
      return response.data;
    } catch (error) {
      console.error(`Error deleting dashboard ${id}:`, error);
      throw error;
    }
  }
  

  async changeActiveDashboard(newId) {
      if (this.useMockData) {
        return new Promise((resolve) => {
          const dashboardWidgets = mockWidgets.filter(c => c.dashboard_id === parseInt(newId));
          setTimeout(() => resolve(dashboardWidgets), 100);
        });
      }
      
      try {
          // Send the ID in the request body as 'newDashboardId'
          const response = await this.apiClient.post(
              '/dashboard/change-active', 
              { newDashboardId: newId } // The ID is passed here in the body
          ); 
          return response.data; 
      } catch (error) {
          console.error('Error changing active dashboard:', error);
          throw error;
      }
  }

  // --- WIDGET ENDPOINTS ---

  async createWidget(dashboard_id, settings) {
    try {
      // Endpoint is /api/dashboard/create-widget, uses POST and expects body data
      const response = await this.apiClient.post('/dashboard/create-widget', {
        dashboard_id: dashboard_id,
        title: settings.title,
        chart_type: null, 
        
        // The controller expects the 'config' to be a string (likely JSON), so we stringify it.
        config: JSON.stringify(settings.config), 
      });
      return response.data;
    } catch (error) {
      console.error('Error creating widget:', error);
      throw error;
    }
  }

  async updateWidgetSettings(settings) {
    try {
      // Endpoint is /api/dashboard-widget/update-settings/{widgetId}
      const response = await this.apiClient.post(
        `/dashboard-widget/update-settings`, 
        {
          widget_id: settings.widget_id,
          title: settings.title,
          chart_type: settings.chart_type,
          dashboard_id: settings.dashboard_id,
          filter_id: settings.filter_id,
          timeframe: settings.timeframe,
          config: settings.config ? JSON.stringify(settings.config) : undefined
        }
      );
      return response.data;
    } catch (error) {
      console.error(`Error updating widget settings ${widgetId}:`, error);
      throw error;
    }
  }

  /**
   * DELETE /delete-widget/{widgetId} (actionDeleteWidget)
   * Deletes an existing widget.
   * @param {number} widgetId The widget ID.
   */
  async deleteWidget(widgetId) {
    try {
      // Endpoint is /api/dashboard/delete-widget/{widgetId}, uses DELETE
      const response = await this.apiClient.delete(`/dashboard/delete-widget/${widgetId}`);
      return response.data;
    } catch (error) {
      console.error(`Error deleting widget ${widgetId}:`, error);
      throw error;
    }
  }

  /**
   * POST /update-widget-layouts (actionUpdateWidgetLayouts)
   * Updates the layout positions for multiple widgets
   * @param {number} dashboardId The dashboard ID
   * @param {Array} layouts Array of layout objects with widget_id, x, y, w, h
   */
  async updateWidgetLayouts(dashboardId, widgetsPositionalInformation) {
    try {
      const response = await this.apiClient.post('/dashboard/update-widget-layout', {
        dashboard_id: dashboardId,
        widgetsPositionalInformation: widgetsPositionalInformation
      });
      return response.data;
    } catch (error) {
      console.error('Error updating widget layouts:', error);
      throw error;
    }
  }

  /**
   * GET /filters (actionGetFilters)
   * Retrieves all available filters for the current user.
   * Note: This endpoint needs to be implemented in your backend.
   */
  async getFilters() {
    try {
      const response = await this.apiClient.get('/dashboard/filters');
      return response.data;
    } catch (error) {
      console.error('Error getting filters:', error);
      throw error;
    }
  }

  async getAllSecurityEventFields() {
    try {
      const response = await this.apiClient.get('/dashboard-widget/all-security-event-fields');
      return response.data;
    } catch (error) {
      console.error('Error getting filters:', error);
      throw error;
    }
  }



  /**
   * GET /widget-content (actionGetWidgetContent)
   * Retrieves the content for a specific widget based on filter and data type.
   * @param {number} widgetId The widget ID.
   * @param {number} filterId The filter ID to apply.
   * @param {string} dataType The type of data to retrieve (table, barChart, pieChart).
   * @param {string} dataParam Additional parameters for data retrieval.
   * @param {number} page The page number for pagination.
   */
  async getWidgetContent(widgetId, page = 1) {
    if (this.useMockData) {
      return new Promise((resolve) => {
        const mockContent = getMockContent(filterId, dataType);
        setTimeout(() => resolve(mockContent), 100);
      });
    }
    
    try {
      const response = await this.apiClient.get('/dashboard-widget/content', {
        params: {
          widgetId,
          page
        }
      });
      return response.data;
    } catch (error) {
      console.error('Error getting widget content:', error);
      throw error;
    }
  }
}

export default new DashboardAPI();