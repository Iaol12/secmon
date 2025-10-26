#!/bin/bash

# SecMon React Dashboard Installation Script
# This script installs Node.js, npm dependencies, and builds the React dashboard

set -e

echo "================================================"
echo "SecMon React Dashboard Installation"
echo "================================================"
echo ""

# Check if we're in the correct directory
if [ ! -f "package.json" ]; then
    echo "Error: package.json not found. Please run this script from the secmon root directory."
    exit 1
fi

# Check if Node.js is installed
if ! command -v node &> /dev/null; then
    echo "Node.js is not installed. Installing Node.js 20.x..."
    
    # Detect OS
    if [ -f /etc/debian_version ]; then
        # Debian/Ubuntu
        curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
        sudo apt-get install -y nodejs
    elif [ -f /etc/redhat-release ]; then
        # RHEL/CentOS/Rocky
        curl -fsSL https://rpm.nodesource.com/setup_20.x | sudo bash -
        sudo yum install -y nodejs
    else
        echo "Unsupported OS. Please install Node.js 20.x manually."
        exit 1
    fi
else
    echo "Node.js is already installed: $(node --version)"
fi

# Check Node.js version
NODE_VERSION=$(node --version | cut -d'v' -f2 | cut -d'.' -f1)
if [ "$NODE_VERSION" -lt 16 ]; then
    echo "Warning: Node.js version $NODE_VERSION is older than required (16+)"
    echo "Please upgrade Node.js to version 16 or higher."
    exit 1
fi

echo ""
echo "Installing npm dependencies..."
npm install

echo ""
echo "Building React dashboard..."
npm run build

echo ""
echo "================================================"
echo "Installation Complete!"
echo "================================================"
echo ""
echo "The React dashboard has been built to: web/js/dist/dashboard-bundle.js"
echo ""
echo "To use the React dashboard:"
echo "1. Backup the current view: mv views/view/index.php views/view/index_old.php"
echo "2. Activate React view: mv views/view/index_new_react.php views/view/index.php"
echo "3. Refresh your browser"
echo ""
echo "Development commands:"
echo "  npm run dev    - Build with watch mode (auto-rebuild on changes)"
echo "  npm run build  - Production build (minified)"
echo "  npm start      - Development server with hot reload"
echo ""
