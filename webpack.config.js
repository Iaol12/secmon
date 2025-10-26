const path = require('path');

module.exports = {
  entry: './web/react/index.jsx',
  output: {
    path: path.resolve(__dirname, 'web/js/dist'),
    filename: 'dashboard-bundle.js',
    publicPath: '/js/dist/'
  },
  module: {
    rules: [
      {
        test: /\.(js|jsx)$/,
        exclude: /node_modules/,
        use: {
          loader: 'babel-loader',
          options: {
            presets: ['@babel/preset-env', '@babel/preset-react']
          }
        }
      },
      {
        test: /\.css$/,
        use: ['style-loader', 'css-loader']
      }
    ]
  },
  resolve: {
    extensions: ['.js', '.jsx']
  },
  devtool: 'source-map',
  devServer: {
    static: {
      directory: path.join(__dirname, 'web'),
    },
    compress: true,
    port: 9000,
    hot: true
  }
};
