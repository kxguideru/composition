<?php

define('THUMB_WIDTH', 150);
define('THUMB_HEIGHT', 150);

define('ROOT_PATH', __DIR__);
define('OUTPUT_PATH', ROOT_PATH . '/www');
define('SRC_PATH', ROOT_PATH . '/src');

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
        '/js',
        '/audio',
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

    $ext = '.' . pathinfo($source, PATHINFO_EXTENSION);
    $thumb = $dest_path . '/thumbs/thumb-' . basename($filename, $ext) . '.jpg';

    if (!file_exists($dest) || !file_exists($thumb) ||
        filemtime($dest) < filemtime($source) ||
        filemtime($thumb) < filemtime($source)
    ) {
        copy($source, $dest);
        create_thumbnail($dest, $thumb, THUMB_HEIGHT);
//        $imagick = new \Imagick(realpath($dest));
//        $imagick->setBackgroundColor('rgb(64, 64, 64)');
//        $imagick->thumbnailImage(THUMB_WIDTH, THUMB_HEIGHT, true);
//        $blob = $imagick->getImageBlob();
//        $imagick->clear();
//        file_put_contents($thumb, $blob);
    }
}

/**
 * Imagick::thumbnailImage() replacement
 *
 * @param $source_file An image
 * @param $thumb_file Should be .jpg
 * @param $size
 */
function create_thumbnail($original_file, $thumb_file, $size)
{
    // create new Imagick object
    $image = new Imagick($original_file);

    // Resizes to whichever is larger, width or height
    if($image->getImageHeight() <= $image->getImageWidth())
    {
        // Resize image using the lanczos resampling algorithm based on width
        $image->resizeImage($size,0,Imagick::FILTER_LANCZOS,1);
    }
    else
    {
        // Resize image using the lanczos resampling algorithm based on height
        $image->resizeImage(0,$size,Imagick::FILTER_LANCZOS,1);
    }

    // Set to use jpeg compression
    $image->setImageCompression(Imagick::COMPRESSION_JPEG);
    // Set compression level (1 lowest quality, 100 highest quality)
    $image->setImageCompressionQuality(75);
    // Strip out unneeded meta data
    $image->stripImage();
    // Writes resultant image to output directory
    $image->writeImage($thumb_file);
    // Destroys Imagick object, freeing allocated resources in the process
    $image->destroy();
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
    $ext = '.' . pathinfo($filename, PATHINFO_EXTENSION);
    $thumb_image = '<img src="images/thumbs/thumb-' . basename($filename, $ext) . '.jpg" alt="' . $alt_text . '">';

    $lightbox = "<a href=\"$real_url\" data-toggle=\"lightbox\" data-title=\"Изображение: $filename\" class=\"img-thumbnail\">$thumb_image</a>";
    return $lightbox;
}

function audio_callback($matches)
{
    $filename = $matches[1];
    $caption = $matches[2];
    $audio_tag = "<audio controls>\n" .
        "<source src=\"audio/$filename\" type=\"audio/mpeg\" />\n" .
        "<a href=\"audio/$filename\">Пример - $caption</a>\n" .
        "Для воспроизведения аудио требуется браузер с поддержкой HTML5.\n" .
        "</audio>";
    return $audio_tag;
}

/**
 * Callback for template pattern, insert file
 *
 * @param $matches
 * @return mixed
 */
function template_callback($matches)
{
    $image_pattern = '/\<img\s+src=\"images\/(([^\.]+)\.png)\"[^\>]+\>/';
    $audio_pattern = '/\<a\s+href=\"audio\/([^\.]+\.mp3)\"[^\>]*\>([^\<]+)\<\/a\>/';
    $filename = $matches[1];
    $html = file_get_contents('src/' . $filename);
    $html = preg_replace_callback($image_pattern, "image_callback", $html);
    $html = preg_replace_callback($audio_pattern, "audio_callback", $html);
    return $html;
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

    if (!file_exists($path_out)) {
        mkdir($path_out, 0777, true);
    }

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

    copy_file($path_src, $path_out, 'images/background.png');

    $dirs = array(
        '/audio',
        '/css',
        '/js',
        '/presets',
    );

    foreach ($dirs as $dir) {
        copy_files($path_src . $dir, $path_out . $dir);
    }

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
