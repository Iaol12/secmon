import { useState, useEffect } from 'react';
import api from '../services/api';

export const useWidgetSettingsForm = (widget, config) => {
  // Single state object for all form data
  const [formData, setFormData] = useState({
    title: widget.title || '',
    chartType: widget.chart_type ,
    timeframe: widget.timeframe || '1D',
    filterId: widget.filter_id || '',
    dashboardId: widget.dashboard_id,
    config: {
      table_columns: config.table_columns || [],
      pie_chart_variable: config.pie_chart_variable || 'cef_severity',
      bar_chart_variable: config.bar_chart_variable || '',
      show_labels: config.show_labels !== undefined ? config.show_labels : true,
      granularity: config.granularity || '1h',
      ...config
    }
  });

  // Available filters for selection
  const [filters, setFilters] = useState([]);
  const [isLoadingFilters, setIsLoadingFilters] = useState(true);

  // Available variables for pie chart and table
  const [availableVariables, setAvailableVariables] = useState([]);
  const [isLoadingVariables, setIsLoadingVariables] = useState(false);

  useEffect(() => {
    loadFilters();
  }, []);

  useEffect(() => {
    if(availableVariables.length === 0)
    {
      loadAvailableVariables();
      }
  }, [formData.chartType]);

  const loadFilters = async () => {
    try {
      const filterData = await api.getFilters();
      setFilters(filterData || []);
    } catch (error) {
      console.error('Error loading filters:', error);
      setFilters([]);
    } finally {
      setIsLoadingFilters(false);
    }
  };

  const loadAvailableVariables = async () => {
    setIsLoadingVariables(true);
    try {
      const response = await api.getAllSecurityEventFields();
      const variables = response?.fields || [];
      setAvailableVariables(variables);
    } catch (error) {
      console.error('Error loading available variables:', error);
      setAvailableVariables([]);
    } finally {
      setIsLoadingVariables(false);
    }
  };

  // Helper function to get default granularity based on timeframe
  const getDefaultGranularityForTimeframe = (timeframe) => {
    switch (timeframe) {
      case '1D':
        return '1h';    // 1 day → hourly data (24 points)
      case '1W':
        return '1d';    // 1 week → daily data (7 points)
      case '1M':
        return '1d';    // 1 month → daily data (~30 points)
      case '3M':
        return '1w';    // 3 months → weekly data (~12 points)
      case '1Y':
        return '1m';    // 1 year → monthly data (12 points)
      default:
        return '1h';
    }
  };

  // Helper function to update top-level form fields
  const updateFormField = (field, value) => {
    setFormData(prev => {
      const updated = { ...prev, [field]: value };
      
      // If timeframe is changed and it's a lineChart, auto-set granularity
      if (field === 'timeframe' && prev.chartType === 'lineChart') {
        const defaultGranularity = getDefaultGranularityForTimeframe(value);
        updated.config = { ...prev.config, granularity: defaultGranularity };
      }
      
      return updated;
    });
  };

  // Helper function to update nested config fields
  const updateConfigField = (field, value) => {
    setFormData(prev => ({
      ...prev,
      config: { ...prev.config, [field]: value }
    }));
  };

  const buildConfigForSubmit = () => {
    let dynamicConfig = {};
    const currentConfig = formData.config;

    // Build the config object based on the chart type
    switch (formData.chartType) {
      case 'table':
        if (currentConfig.table_columns && currentConfig.table_columns.length > 0) {
          dynamicConfig.table_columns = currentConfig.table_columns;
        }
        break;
        
      case 'pieChart':
        dynamicConfig.pie_chart_variable = currentConfig.pie_chart_variable;
        dynamicConfig.show_labels = currentConfig.show_labels;
        break;

      case 'barChart':
        dynamicConfig.bar_chart_variable = currentConfig.bar_chart_variable;
        break;

      case 'lineChart':
        dynamicConfig.granularity = currentConfig.granularity;
        break;

      default:
        break;
    }

    return dynamicConfig;
  };

  const getSubmitPayload = () => ({
    widget_id: widget.id,
    title: formData.title,
    filter_id: formData.filterId,
    dashboard_id: formData.dashboardId,
    chart_type: formData.chartType,
    timeframe: formData.timeframe,
    config: buildConfigForSubmit()
  });

  return {
    formData,
    filters,
    isLoadingFilters,
    availableVariables,
    isLoadingVariables,
    updateFormField,
    updateConfigField,
    getSubmitPayload
  };
};
