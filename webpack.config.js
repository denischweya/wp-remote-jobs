const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');
const path = require('path');

module.exports = {
    ...defaultConfig,
    entry: {
        index: path.resolve(process.cwd(), defaultConfig.entry),
        style: path.resolve(process.cwd(), './src/style.scss'),
        editor: path.resolve(process.cwd(), './src/editor.scss')
    },
    module: {
        ...defaultConfig.module,
        rules: [
            ...defaultConfig.module.rules,
            {
                test: /\.scss$/,
                include: /src/,
                use: [
                    MiniCssExtractPlugin.loader,
                    {
                        loader: 'css-loader',
                        options: {
                            url: false,
                            sourceMap: true
                        }
                    },
                    {
                        loader: 'sass-loader',
                        options: {
                            sourceMap: true,
                            sassOptions: {
                                outputStyle: 'compressed'
                            }
                        }
                    }
                ]
            }
        ]
    },
    plugins: [
        ...defaultConfig.plugins,
        new MiniCssExtractPlugin({
            filename: '[name].css'
        })
    ],
    optimization: {
        ...defaultConfig.optimization,
        minimizer: [
            ...defaultConfig.optimization.minimizer,
            new CssMinimizerPlugin()
        ]
    }
}; 