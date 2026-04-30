import gulp from 'gulp';
import gulpSass from 'gulp-sass';
import cleanCSS from 'gulp-clean-css';
import autoprefixer from 'gulp-autoprefixer';
import * as dartSass from 'sass';
import uglify from 'gulp-uglify';
import rename from 'gulp-rename';
import merge from 'merge-stream';
import sourcemaps from 'gulp-sourcemaps';
import gulpIf from 'gulp-if';
import { rollup } from 'rollup';
import { babel } from '@rollup/plugin-babel';
import terser from '@rollup/plugin-terser';
import resolve from '@rollup/plugin-node-resolve';

const sass = gulpSass(dartSass);

const isProduction = process.env.NODE_ENV === 'production';

const config = {
    sourceMaps: !isProduction,
    cleanCSS: isProduction
}

// Task to compile Sass and minify CSS
gulp.task('build-css', function () {
    return gulp.src('dev/scss/**/*.scss')
        .pipe(gulpIf(config.sourceMaps, sourcemaps.init()))
        .pipe(sass().on('error', sass.logError))
        .pipe(autoprefixer({
            cascade: false
        }))
        .pipe(gulpIf(config.cleanCSS, cleanCSS()))
        .pipe(rename({ suffix: '.min' }))
        .pipe(gulpIf(config.sourceMaps, sourcemaps.write()))
        .pipe(gulp.dest('assets/css'));
});

// Task to bundle and minify JavaScript using Rollup
gulp.task('build-js', async function () {
    const mainBundle = await rollup({
        input: 'dev/js/main.js',
        external: ['@wordpress/i18n'],
        plugins: [
            resolve(),
            babel({
                babelHelpers: 'bundled',
                presets: ['@babel/preset-env'],
            }),
            ...(isProduction ? [terser({
                mangle: {
                    reserved: ['__', '_x', '_n', '_nx', 'sprintf']
                }
            })] : []),
        ],
        onwarn(warning, warn) {
            // Suppress warnings for WordPress/browser globals (jQuery, ajaxurl, etc.)
            if (warning.code === 'MISSING_GLOBAL_NAME') return;
            warn(warning);
        }
    });

    await mainBundle.write({
        file: 'assets/js/main.min.js',
        format: 'iife',
        sourcemap: config.sourceMaps,
        globals: {
            '@wordpress/i18n': 'wp.i18n'
        }
    });

    // Libraries: Process spectrum.js (standalone, no bundling needed)
    const spectrumJsStream = gulp.src('dev/js/lib/spectrum.js')
        .pipe(uglify())
        .pipe(rename({ suffix: '.min' }))
        .pipe(gulp.dest('assets/js'));

    return merge(spectrumJsStream);
});

// Task to watch for changes in JS and Sass files
gulp.task('watch', function () {
    gulp.watch('dev/scss/**/*.scss', gulp.series('build-css'));
    gulp.watch('dev/js/**/*.js', gulp.series('build-js'));
});

// Default task
if (isProduction) {
    gulp.task('default', gulp.series('build-css', 'build-js'));
} else {
    gulp.task('default', gulp.series('build-css', 'build-js', 'watch'));
}
