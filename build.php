<?php

define('THUMB_WIDTH', 150);
define('THUMB_HEIGHT', 150);

define('ROOT_PATH', __DIR__);
define('OUTPUT_PATH', ROOT_PATH . '/www');
define('SRC_PATH', ROOT_PATH . '/src');

define('MSG_OPEN_IMAGE', 'Показать изображение');

/**
 * Cleanup output
 *
 * @param $output_path
 */
function cleanup($output_path)
{
    $paths = array(
        '',
        '/images',
        '/images/thumbs',
        '/css',
        '/presets',
    );

    foreach ($paths as $path) {
        cleanup_dir($output_path . $path);
    }
}

/**
 * Cleanup directory
 *
 * @param $path
 */
function cleanup_dir($path)
{
    if (!file_exists($path)) {
        mkdir($path, 0777, true);
    }

    $files = glob("$path/*");

    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
}

/**
 * Copy image and create thumbnail
 *
 * @param $source_path
 * @param $dest_path
 * @param $filename
 */
function copy_image($source_path, $dest_path, $filename)
{
    $source = $source_path . '/' . $filename;
    $dest = $dest_path . '/' . $filename;
    $thumb = $dest_path . '/thumbs/thumb-' . $filename;

    if (!file_exists($dest) || !file_exists($thumb) ||
        filemtime($dest) < filemtime($source) ||
        filemtime($thumb) < filemtime($source)
    ) {
        copy($source, $dest);
        $imagick = new \Imagick(realpath($dest));
        $imagick->setBackgroundColor('rgb(64, 64, 64)');
        $imagick->thumbnailImage(THUMB_WIDTH, THUMB_HEIGHT, true);
        $blob = $imagick->getImageBlob();
        file_put_contents($thumb, $blob);
    }
}

/**
 * Callback for image search pattern, create thumbnail and lightbox
 *
 * @param $matches
 * @return string
 */
function image_callback($matches)
{
    $filename = $matches[1];
    $image_name = $matches[2];
    copy_image(SRC_PATH . '/images', OUTPUT_PATH . '/images', $filename);
    $alt_text = ucwords(str_replace('-', ' ', $image_name));

    $real_url = 'images/' . $filename;
    $thumb_image = '<img src="images/thumbs/thumb-' . $filename . '" alt="' . $alt_text . '">';

    $lightbox = "<a href=\"$real_url\" data-toggle=\"lightbox\" data-title=\"Изображение: $filename\" class=\"img-thumbnail\">$thumb_image</a>";
    return $lightbox;
}

/**
 * Callback for template pattern, insert file
 *
 * @param $matches
 * @return mixed
 */
function template_callback($matches)
{
    $filename = $matches[1];
    $html = file_get_contents('src/' . $filename);
    $image_pattern = '/\<img src=\"images\/(([^\.]+)\.png)\"[^\>]+\>/';
    return preg_replace_callback($image_pattern, "image_callback", $html);
}

/**
 * Copy file
 *
 * @param $path_src
 * @param $path_out
 * @param $relative
 */
function copy_file($path_src, $path_out, $relative)
{
    $out = $path_out . '/' . $relative;
    $src = $path_src . '/' . $relative;

    if (!file_exists($out) || filemtime($out) < filemtime($src)) {
        copy($src, $out);
    }
}

/**
 * Copy all found files
 *
 * @param $path_src
 * @param $path_out
 */
function copy_files($path_src, $path_out)
{
    $files = glob("$path_src/*");

    foreach ($files as $file) {
        if (is_file($file)) {
            copy_file($path_src, $path_out, basename($file));
        }
    }
}

/**
 * Build output
 *
 * @param $path_src
 * @param $path_out
 */
function build($path_src, $path_out)
{
    $template_file = $path_src . '/template.html';
    $index_file = $path_out . '/index.html';

    copy_file($path_src, $path_out, 'css/style.css');
    copy_file($path_src, $path_out, 'images/background.png');
    copy_files($path_src . '/presets', $path_out . '/presets');

    $template = file_get_contents($template_file);
    $html = preg_replace_callback('/\{\{([^}]+)\}\}/', "template_callback", $template);
    file_put_contents($index_file, $html);
}

/**
 * Entry point
 *
 * @param $argc
 * @param $argv
 */
function main($argc, $argv)
{
    if ($argc > 1) {
        $job = $argv[1];

        switch ($job) {
            case "clean":
                echo "Cleaning up...\n";
                cleanup(OUTPUT_PATH);
                return;

            case "build":
                echo "Building...\n";
                build(SRC_PATH, OUTPUT_PATH);
                return;

            default:
        }
    }

    echo "Usage: php build.php {clean|build}\n\n";
}

main($argc, $argv);
