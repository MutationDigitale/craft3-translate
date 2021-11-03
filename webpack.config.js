// webpack.config.js
const { VueLoaderPlugin } = require('vue-loader');
const path = require('path');
const webpack = require('webpack');

const env = process.env.NODE_ENV;

const config = {
  entry: path.join(__dirname, 'assets', 'main.js'),
  mode: env,
  output: {
    path: path.join(__dirname, 'src/resources/')
  },
  module: {
    rules: [
      {
        test: /\.vue$/,
        loader: 'vue-loader',
        options: {
          compilerOptions: {
            compatConfig: {
              MODE: 3
            }
          }
        }
      },
      {
        test: /\.js$/,
        loader: 'babel-loader',
        include: path.join(__dirname, 'src'),
      },
      {
        test: /\.css$/,
        use: [
          'vue-style-loader',
          'css-loader',
        ],
      },
      {
        test: /\.scss$/,
        use: [
          'vue-style-loader',
          'css-loader',
          'sass-loader'
        ]
      }
    ],
  },
  resolve: {
    alias: {
      vue: '@vue/compat/dist/vue.esm-bundler.js'
    }
  },
  plugins: [
    new VueLoaderPlugin(),
    new webpack.DefinePlugin({
      __VUE_OPTIONS_API__: true,
      __VUE_PROD_DEVTOOLS__: false
    }),
  ],
};

module.exports = config;
