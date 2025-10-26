# React Dashboard Quick Start Guide

## Overview

The SecMon dashboard has been modernized with React, Recharts, and modern web technologies, replacing the legacy jQuery/D3.js implementation.

## What's New

### ✨ Modern Features
- **React 18** - Latest React with hooks and concurrent rendering
- **Recharts** - Beautiful, responsive charts that replace D3.js
- **React Grid Layout** - Smooth drag-and-drop widget repositioning
- **Modern UI** - Clean, Material Design-inspired interface
- **Better Performance** - Faster rendering and smoother animations

### 🎨 Visual Improvements
- Smoother animations and transitions
- Better color schemes and visual hierarchy
- Responsive design for all screen sizes
- Cleaner widget cards with modern styling
- Interactive tooltips with detailed information

### 🚀 Technical Improvements
- Component-based architecture
- Better code organization and maintainability
- Type-safe data handling
- Optimized bundle with code splitting
- Better error handling and loading states

## Installation (In Docker Container)

The Dockerfile has been updated to automatically install Node.js and build the React dashboard. When you rebuild your container, everything will be set up automatically.

### Manual Installation (if needed)

If you need to install manually or are not using Docker:

```bash
# Run the installation script
./install-react-dashboard.sh
```

Or manually:

```bash
# Install dependencies
npm install

# Build the dashboard
npm run build
```

## Activation

To switch from the old dashboard to the React dashboard:

```bash
# 1. Backup the old view
mv views/view/index.php views/view/index_old.php

# 2. Activate the React view
mv views/view/index_new_react.php views/view/index.php

# 3. Refresh your browser - that's it!
```

To revert back to the old dashboard:

```bash
# Restore the old view
mv views/view/index.php views/view/index_new_react.php
mv views/view/index_old.php views/view/index.php
```

## Development

### Build Commands

```bash
# Development build with watch mode (rebuilds on file changes)
npm run dev

# Production build (minified and optimized)
npm run build

# Development server with hot reload
npm start
```

### File Structure

```
web/react/
├── components/          # React components
│   ├── charts/         # Chart components (Bar, Pie, Table)
│   ├── Dashboard.jsx   # Main dashboard
│   ├── WidgetCard.jsx  # Widget component
│   └── WidgetSettings.jsx
├── services/
│   └── api.js          # Backend API calls
└── index.jsx           # App entry point
```

## Usage

### Creating Widgets

1. Click the red **+** button (bottom-right)
2. A new widget is created
3. Click **Add content** to configure it
4. Select a filter, content type (table/bar chart/pie chart), and parameters
5. Click **Save**

### Configuring Widgets

1. Click the **⚙️ settings** icon on any widget
2. Change the name and width
3. Click **Delete Widget** to remove it

### Editing Widget Content

1. Click the **✏️ edit** icon on widgets with content
2. Change filter, content type, or parameters
3. Click **Delete Content** to clear the widget
4. Click **Save** to apply changes

### Moving Widgets

- Click and drag the widget header to reposition
- Layout automatically adjusts
- Changes are saved automatically

### Switching Dashboards

- Use the dropdown at the top to switch between dashboards
- The old **Create**, **Edit**, and **Delete** buttons still work for dashboard management

## Features Comparison

| Feature | Old (jQuery/D3) | New (React/Recharts) |
|---------|-----------------|----------------------|
| Drag & Drop | Packery | React Grid Layout |
| Charts | D3.js | Recharts |
| State Management | Global variables | React state |
| Responsiveness | Limited | Full responsive |
| Animation | Basic | Smooth & modern |
| Code Maintainability | Hard | Easy |
| Bundle Size | ~400KB | ~500KB |
| Browser Support | Old browsers | Modern browsers |

## Troubleshooting

### Dashboard not loading

**Problem**: Blank page or errors in console

**Solution**:
```bash
# Rebuild the bundle
npm run build

# Check if bundle exists
ls -lh web/js/dist/dashboard-bundle.js

# Check Apache can serve it
curl http://localhost/secmon/web/js/dist/dashboard-bundle.js
```

### Widgets not showing data

**Problem**: Widgets show loading spinner forever

**Solution**:
- Check browser console for API errors
- Verify you have filters configured
- Ensure database connection is working
- Check Yii error logs

### Layout looks broken

**Problem**: Widgets overlap or don't position correctly

**Solution**:
```bash
# Clear browser cache (Ctrl+Shift+Del)
# Rebuild with fresh install
rm -rf node_modules package-lock.json
npm install
npm run build
```

### Build fails

**Problem**: `npm run build` shows errors

**Solution**:
```bash
# Check Node.js version (need 16+)
node --version

# Update npm
npm install -g npm@latest

# Clean install
rm -rf node_modules
npm install
```

## Performance Tips

1. **Auto-refresh**: Set appropriate refresh intervals in dashboard settings
   - Use longer intervals (5m+) for less critical data
   - Use shorter intervals (10s-1m) only for real-time monitoring

2. **Widget count**: Keep dashboards focused
   - Recommended: 4-8 widgets per dashboard
   - Maximum: 12-16 widgets per dashboard

3. **Data filters**: Use specific filters
   - Filter data at the database level
   - Avoid overly broad queries

## Browser Compatibility

| Browser | Minimum Version | Recommended |
|---------|----------------|-------------|
| Chrome | 90+ | 120+ |
| Firefox | 88+ | 120+ |
| Edge | 90+ | 120+ |
| Safari | 14+ | 17+ |

## Support

For issues or questions:

1. Check the browser console for errors
2. Review Yii application logs
3. Check the React README: `web/react/README.md`
4. Verify all npm packages are installed: `npm install`

## Next Steps

After activation, you can:

- [ ] Create multiple dashboards for different use cases
- [ ] Configure auto-refresh intervals
- [ ] Set up widgets with various visualizations
- [ ] Customize widget layouts for your team
- [ ] Explore the modern charting capabilities

Enjoy your modernized dashboard! 🎉
