// webpack.config.js
const {VueLoaderPlugin} = require('vue-loader');
const MiniCssExtractPlugin = require("mini-css-extract-plugin");
const path = require('path');
const webpack = require('webpack');

const env = process.env.NODE_ENV;

const srcDir = path.join(__dirname, 'assets');
const outputDir = path.join(__dirname, 'src', 'resources');

const config = {
  entry: path.join(srcDir, 'main.js'),
  mode: env,
  output: {
    path: outputDir
  },
  devtool: 'source-map',
  module: {
    rules: [
      {
        test: /\.vue$/,
        include: srcDir,
        use: [
          'vue-loader',
        ],
      },
      {
        test: /\.js$/,
        include: srcDir,
        use: [
          'babel-loader',
        ]
      },
      {
        test: /\.css$/,
        include: srcDir,
        use: [
          MiniCssExtractPlugin.loader,
          'css-loader',
        ],
      },
      {
        test: /\.scss$/,
        include: srcDir,
        use: [
          MiniCssExtractPlugin.loader,
          'css-loader',
          'sass-loader',
        ],
      },
      {
        test: /\.(png|jpg|gif)$/i,
        include: srcDir,
        use: [
          'url-loader',
        ],
      },
    ],
  },
  resolve: {
    alias: {
      vue: 'vue/dist/vue.esm-bundler.js'
    }
  },
  plugins: [
    new VueLoaderPlugin(),
    new MiniCssExtractPlugin(),
    new webpack.DefinePlugin({
      __VUE_OPTIONS_API__: true,
      __VUE_PROD_DEVTOOLS__: false
    }),
  ],
};

module.exports = config;
