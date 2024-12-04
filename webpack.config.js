const defaultConfig = require("@wordpress/scripts/config/webpack.config");

module.exports = {
  ...defaultConfig,
  entry: {
    index: './src/index.js'
  },
  output: {
    filename: '[name].js',
    path: __dirname + '/build'
  }
}; 