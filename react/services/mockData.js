// Mock data for dashboard widgets

export const mockDashboards = [
  {
    id: 1,
    name: 'Security Overview',
    active: true,
    refresh_time: '30S',
    user_id: 1,
    created_at: '2025-12-01',
    updated_at: '2025-12-08'
  },
  {
    id: 2,
    name: 'Network Traffic',
    active: false,
    refresh_time: '1m',
    user_id: 1,
    created_at: '2025-12-02',
    updated_at: '2025-12-08'
  }
];

export const mockWidgets = [
  {
    id: 1,
    dashboard_id: 1,
    order: 0,
    config: JSON.stringify({
      name: 'Threat Types',
      width: 'width2',
      filter_id: 'mock_pie',
      data_type: 'pieChart',
      data_param: ''
    })
  },
  {
    id: 2,
    dashboard_id: 1,
    order: 1,
    config: JSON.stringify({
      name: 'Security Events by Severity',
      width: 'width2',
      filter_id: 'mock_bar',
      data_type: 'barChart',
      data_param: ''
    })
  },
  {
    id: 3,
    dashboard_id: 1,
    order: 2,
    config: JSON.stringify({
      name: 'Traffic Over Time',
      width: 'width4',
      filter_id: 'mock_line',
      data_type: 'lineChart',
      data_param: ''
    })
  },
  {
    id: 4,
    dashboard_id: 1,
    order: 3,
    config: JSON.stringify({
      name: 'Recent Incidents',
      width: 'width4',
      filter_id: 'mock_table',
      data_type: 'table',
      data_param: ''
    })
  },
  {
    id: 5,
    dashboard_id: 2,
    order: 0,
    config: JSON.stringify({
      name: 'Network Protocols',
      width: 'width2',
      filter_id: 'mock_pie2',
      data_type: 'pieChart',
      data_param: ''
    })
  },
  {
    id: 6,
    dashboard_id: 2,
    order: 1,
    config: JSON.stringify({
      name: 'Bandwidth Usage',
      width: 'width2',
      filter_id: 'mock_bar2',
      data_type: 'barChart',
      data_param: ''
    })
  }
];

// Mock data for Pie Chart - Threat Types
export const mockPieChartData = [
  { label: 'Malware', count: 45 },
  { label: 'Phishing', count: 32 },
  { label: 'DDoS', count: 28 },
  { label: 'SQL Injection', count: 15 },
  { label: 'XSS', count: 12 },
  { label: 'Brute Force', count: 8 }
];

// Mock data for Bar Chart - Security Events by Severity
export const mockBarChartData = [
  { x: 'Critical', y: 23 },
  { x: 'High', y: 45 },
  { x: 'Medium', y: 67 },
  { x: 'Low', y: 89 },
  { x: 'Info', y: 134 }
];

// Mock data for Line Chart - Traffic Over Time
export const mockLineChartData = [
  { x: '00:00', y: 120 },
  { x: '04:00', y: 85 },
  { x: '08:00', y: 230 },
  { x: '12:00', y: 310 },
  { x: '16:00', y: 280 },
  { x: '20:00', y: 195 }
];

// Mock data for another Pie Chart - Network Protocols
export const mockPieChartData2 = [
  { label: 'HTTP', count: 450 },
  { label: 'HTTPS', count: 1200 },
  { label: 'FTP', count: 80 },
  { label: 'SSH', count: 120 },
  { label: 'DNS', count: 340 }
];

// Mock data for another Bar Chart - Bandwidth Usage
export const mockBarChartData2 = [
  { x: 'Monday', y: 450 },
  { x: 'Tuesday', y: 520 },
  { x: 'Wednesday', y: 480 },
  { x: 'Thursday', y: 590 },
  { x: 'Friday', y: 610 },
  { x: 'Saturday', y: 380 },
  { x: 'Sunday', y: 320 }
];

// Mock HTML table data
export const mockTableHTML = `
  <table class="data-table">
    <thead>
      <tr>
        <th>Timestamp</th>
        <th>Event Type</th>
        <th>Severity</th>
        <th>Source IP</th>
        <th>Destination IP</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>2025-12-08 14:32:15</td>
        <td>Port Scan</td>
        <td><span class="severity-high">High</span></td>
        <td>192.168.1.100</td>
        <td>10.0.0.50</td>
        <td><span class="status-blocked">Blocked</span></td>
      </tr>
      <tr>
        <td>2025-12-08 14:28:43</td>
        <td>Malware Detection</td>
        <td><span class="severity-critical">Critical</span></td>
        <td>172.16.0.25</td>
        <td>10.0.0.100</td>
        <td><span class="status-quarantined">Quarantined</span></td>
      </tr>
      <tr>
        <td>2025-12-08 14:15:22</td>
        <td>Failed Login</td>
        <td><span class="severity-medium">Medium</span></td>
        <td>203.0.113.45</td>
        <td>10.0.0.10</td>
        <td><span class="status-logged">Logged</span></td>
      </tr>
      <tr>
        <td>2025-12-08 14:10:11</td>
        <td>SQL Injection Attempt</td>
        <td><span class="severity-high">High</span></td>
        <td>198.51.100.78</td>
        <td>10.0.0.25</td>
        <td><span class="status-blocked">Blocked</span></td>
      </tr>
      <tr>
        <td>2025-12-08 14:05:33</td>
        <td>DDoS Attack</td>
        <td><span class="severity-critical">Critical</span></td>
        <td>Multiple Sources</td>
        <td>10.0.0.1</td>
        <td><span class="status-mitigated">Mitigated</span></td>
      </tr>
      <tr>
        <td>2025-12-08 13:58:19</td>
        <td>Suspicious Traffic</td>
        <td><span class="severity-low">Low</span></td>
        <td>192.168.2.50</td>
        <td>10.0.0.75</td>
        <td><span class="status-monitoring">Monitoring</span></td>
      </tr>
      <tr>
        <td>2025-12-08 13:45:07</td>
        <td>File Upload Blocked</td>
        <td><span class="severity-medium">Medium</span></td>
        <td>10.0.1.120</td>
        <td>10.0.0.200</td>
        <td><span class="status-blocked">Blocked</span></td>
      </tr>
      <tr>
        <td>2025-12-08 13:30:44</td>
        <td>Unauthorized Access</td>
        <td><span class="severity-high">High</span></td>
        <td>172.16.5.88</td>
        <td>10.0.0.15</td>
        <td><span class="status-blocked">Blocked</span></td>
      </tr>
    </tbody>
  </table>
`;

// Function to get mock content based on filter_id
export const getMockContent = (filterId, dataType) => {
  const mockDataMap = {
    'mock_pie': {
      contentTypeId: 'pieChart',
      data: JSON.stringify(mockPieChartData),
      html: null
    },
    'mock_bar': {
      contentTypeId: 'barChart',
      data: JSON.stringify(mockBarChartData),
      html: null
    },
    'mock_line': {
      contentTypeId: 'lineChart',
      data: JSON.stringify(mockLineChartData),
      html: null
    },
    'mock_table': {
      contentTypeId: 'table',
      data: null,
      html: mockTableHTML
    },
    'mock_pie2': {
      contentTypeId: 'pieChart',
      data: JSON.stringify(mockPieChartData2),
      html: null
    },
    'mock_bar2': {
      contentTypeId: 'barChart',
      data: JSON.stringify(mockBarChartData2),
      html: null
    }
  };

  return mockDataMap[filterId] || {
    contentTypeId: dataType,
    data: JSON.stringify([]),
    html: '<p>No data available</p>'
  };
};
