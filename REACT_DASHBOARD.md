# React Dashboard Implementation

This document describes the modern React-based dashboard implementation for SecMon.

## Overview

The dashboard has been modernized using React and modern charting libraries, replacing the old jQuery/D3.js implementation with a more maintainable and performant solution.

## Technology Stack

- **React 18.2** - Modern UI framework
- **Recharts 2.10** - Modern, responsive charting library
- **React Grid Layout** - Drag-and-drop grid layout system
- **Axios** - HTTP client for API calls
- **Webpack 5** - Module bundler
- **Babel** - JavaScript transpiler

## Architecture

### Component Structure

```
web/react/
├── index.jsx                  # Entry point, initializes React app
├── index.css                  # Global styles
├── components/
│   ├── Dashboard.jsx          # Main dashboard container
│   ├── Dashboard.css
│   ├── WidgetCard.jsx         # Individual widget component
│   ├── WidgetCard.css
│   ├── WidgetSettings.jsx     # Widget configuration modal
│   └── charts/
│       ├── BarChart.jsx       # Bar chart using Recharts
│       ├── PieChart.jsx       # Pie chart using Recharts
│       ├── DataTable.jsx      # Table view
│       └── DataTable.css
└── services/
    └── api.js                 # API service layer for backend communication
```

### Key Features

#### 1. Modern Chart Components
- **BarChart**: Responsive bar charts with tooltips, legends, and animations
- **PieChart**: Interactive pie charts with percentage display
- **DataTable**: Styled data tables with pagination support

#### 2. Drag-and-Drop Grid Layout
- Uses `react-grid-layout` for responsive, draggable widgets
- Supports multiple breakpoints (mobile, tablet, desktop)
- Automatically saves widget positions

#### 3. Widget Management
- Create, update, and delete widgets
- Configure widget content (filter, chart type, parameters)
- Resize and reposition widgets
- Real-time data updates

#### 4. Dashboard Management
- Switch between multiple dashboards
- Auto-refresh with configurable intervals
- Responsive design for all screen sizes

## Installation & Setup

### 1. Install Dependencies

```bash
cd /home/vagrant/secmon
npm install
```

### 2. Build for Production

```bash
npm run build
```

This creates an optimized bundle at `web/js/dist/dashboard-bundle.js`

### 3. Development Mode

For development with hot reloading:

```bash
npm run dev
```

### 4. Activate React Dashboard

To use the React dashboard, update your route to use the new view:

**Option A: Replace existing view**
Rename `views/view/index.php` to `views/view/index-old.php` and rename `views/view/index-react.php` to `views/view/index.php`

**Option B: Create new route** 
Add a new action in `controllers/ViewController.php`:

```php
public function actionReactIndex()
{
    return $this->actionIndex(); // Uses index-react.php automatically
}
```

## API Integration

The React app communicates with the existing Yii2 backend through the API service layer (`services/api.js`). All existing endpoints are preserved:

- `view/change-view` - Switch active dashboard
- `view/create-component` - Create new widget
- `view/update-component` - Update widget configuration
- `view/delete-component` - Delete widget
- `view/update-order-of-components` - Save widget positions
- `filter/get-component-content` - Fetch widget data
- `filter/add-filter-to-component` - Configure widget content
- `filter/remove-filter-from-component` - Remove widget content

## Configuration

The React app receives configuration through `window.dashboardConfig`:

```javascript
{
  views: [],           // Array of dashboard views
  activeViewId: 1,     // Currently active dashboard
  filters: [],         // Available filters
  tableColumns: {},    // Available table columns
  urls: {}            // API endpoint URLs
}
```

This configuration is passed from PHP in `views/view/index-react.php`.

## Migration from Old System

### What Changed:
1. **jQuery → React**: Component-based architecture instead of procedural jQuery
2. **D3.js → Recharts**: Declarative, React-native charting
3. **Packery → React Grid Layout**: Modern grid system with better touch support
4. **Materialize modals → React modals**: Native React modal components

### What Stayed the Same:
- All backend API endpoints
- Data structures and database schema
- Filter system and business logic
- Authentication and authorization

## Browser Support

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## Performance Improvements

1. **Virtual DOM**: React's efficient rendering reduces DOM manipulation
2. **Code Splitting**: Webpack optimizes bundle size
3. **Lazy Loading**: Components load on demand
4. **Memoization**: React's optimization prevents unnecessary re-renders
5. **Responsive Charts**: Recharts automatically adapts to container size

## Customization

### Adding New Chart Types

1. Create a new component in `web/react/components/charts/`
2. Import and use Recharts components
3. Add to WidgetCard.jsx's renderContent switch statement

### Styling

- Global styles: `web/react/index.css`
- Component styles: Co-located `.css` files
- Inline styles: For dynamic styling needs

### Grid Layout

Adjust grid configuration in Dashboard.jsx:

```javascript
breakpoints={{ lg: 1200, md: 996, sm: 768, xs: 480, xxs: 0 }}
cols={{ lg: 12, md: 10, sm: 6, xs: 4, xxs: 2 }}
```

## Troubleshooting

### Bundle not loading
- Check that `npm run build` completed successfully
- Verify `web/js/dist/dashboard-bundle.js` exists
- Check browser console for errors

### Widgets not updating
- Verify API endpoints are accessible
- Check network tab for failed requests
- Ensure user is authenticated

### Layout issues
- Clear browser cache
- Rebuild with `npm run build`
- Check responsive breakpoints

## Future Enhancements

- [ ] Dark mode support
- [ ] Export dashboard as PDF/PNG
- [ ] More chart types (line, area, scatter)
- [ ] Real-time WebSocket updates
- [ ] Dashboard templates
- [ ] Widget library/marketplace
- [ ] Advanced filtering in widgets
- [ ] Collaborative dashboards

## Development

### File Watching
```bash
npm run dev
```

### Building for Production
```bash
npm run build
```

### Code Structure
- Keep components small and focused
- Use hooks for state management
- Maintain separation of concerns (UI vs. logic)
- Add PropTypes for type checking (optional)

## Support

For issues or questions, refer to:
- React documentation: https://react.dev
- Recharts documentation: https://recharts.org
- React Grid Layout: https://github.com/react-grid-layout/react-grid-layout
