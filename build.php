<?php

define('THUMB_WIDTH', 150);
define('THUMB_HEIGHT', 150);

define('ROOT_PATH', __DIR__);
define('OUTPUT_PATH', ROOT_PATH . '/www');
define('SRC_PATH', ROOT_PATH . '/src');

/**
 * Create directory if not exists
 *
 * @param string $path
 */
function create_dir($path)
{
    if (!file_exists($path)) {
        mkdir($path, 0777, true);
    }
}

/**
 * Cleanup output
 *
 * @param string $output_path
 */
function cleanup($output_path)
{
    $paths = array(
        '/images/thumbs',
        '/images',
        '/css',
        '/js',
        '/audio',
        '/session',
        '/files',
        '',
    );

    foreach ($paths as $path) {
        cleanup_dir($output_path . $path);
    }
}

/**
 * Check if directory is empty
 *
 * @param $dir
 * @return bool|null
 */
function is_dir_empty($dir) {
    if (!is_readable($dir)) return null;
    $handle = opendir($dir);

    while (false !== ($entry = readdir($handle))) {
        if ($entry != "." && $entry != "..") {
            return false;
        }
    }

    return true;
}

/**
 * Cleanup directory
 *
 * @param string $path
 */
function cleanup_dir($path)
{
    if (file_exists($path) && is_dir($path)) {
        $files = glob("$path/*");

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        if (is_dir_empty($path)) {
            rmdir($path);
        }
    }
}

/**
 * Copy image and create thumbnail
 *
 * @param $source_path
 * @param $output_path
 * @param $filename
 * @return Thumbnail dimensions
 */
function copy_image($source_path, $output_path, $filename)
{
    $source = $source_path . '/' . $filename;
    $output = $output_path . '/' . $filename;

    $ext = '.' . pathinfo($source, PATHINFO_EXTENSION);
    $thumb = $output_path . '/thumbs/thumb-' . basename($filename, $ext) . '.jpg';

    if (!file_exists($output) || !file_exists($thumb) ||
        filemtime($output) < filemtime($source) ||
        filemtime($thumb) < filemtime($source)
    ) {
        copy($source, $output);
        create_thumbnail($output, $thumb, THUMB_HEIGHT);
    }

    $size = getimagesize($thumb);
    return array_slice($size, 0, 2);
}

/**
 * Encode WAV to MP3 using LAME
 *
 * @param string $original_file WAV file
 * @param string $mp3_file Destination MP3 file
 * @param string $options LAME options
 */
function create_mp3($original_file, $mp3_file, $options = '-V2')
{
    if (!file_exists($mp3_file) || filemtime($mp3_file) < filemtime($original_file)) {
        $command = "lame $options $original_file $mp3_file";
        exec($command);
    }
}

/**
 * Imagick::thumbnailImage() replacement
 *
 * @param string $original_file An image
 * @param string $thumb_file Should be .jpg
 * @param int $size
 */
function create_thumbnail($original_file, $thumb_file, $size)
{
    $image = new Imagick($original_file);
    $columns = $image->getImageWidth();
    $rows = $image->getImageHeight();

    if ($rows <= $columns) {
        $columns = $size;
        $rows = 0;
    } else {
        $columns = 0;
        $rows = $size;
    }

    $image->resizeImage($columns, $rows, Imagick::FILTER_LANCZOS, 1);
    $image->setImageCompression(Imagick::COMPRESSION_JPEG);
    $image->setImageCompressionQuality(75);
    $image->stripImage();
    $image->writeImage($thumb_file);
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
    $thumb_size = copy_image(SRC_PATH . '/images', OUTPUT_PATH . '/images', $filename);
    $thumb_style = "width: $thumb_size[0]px; height: $thumb_size[1]px;";
    $alt_text = ucwords(str_replace('-', ' ', $image_name));

    $real_url = 'images/' . $filename;
    $ext = '.' . pathinfo($filename, PATHINFO_EXTENSION);
    $thumb_image = '<img style="' . $thumb_style . '" src="images/thumbs/thumb-' . basename($filename, $ext) . '.jpg" alt="' . $alt_text . '">';

    $lightbox = "<a href=\"$real_url\" data-toggle=\"lightbox\" data-title=\"Изображение: $filename\" class=\"img-thumbnail\">$thumb_image</a>";
    return $lightbox;
}

/**
 * Callback function for audio files
 *
 * @param $matches
 * @return string
 */
function audio_callback($matches)
{
    $filename = $matches[1];
    $caption = $matches[2];

    $ext = '.' . pathinfo($filename, PATHINFO_EXTENSION);
    $mp3_filename = basename($filename, $ext) . '.mp3';
    create_mp3(SRC_PATH . "/audio/$filename", OUTPUT_PATH . "/audio/$mp3_filename");

    $audio_tag = "<audio controls>\n" .
        "<source src=\"audio/$mp3_filename\" type=\"audio/mpeg\" />\n" .
        "<a href=\"audio/$mp3_filename\">$caption</a>\n" .
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
    $audio_pattern = '/\<a\s+href=\"audio\/([^\.]+\.wav)\"[^\>]*\>([^\<]+)\<\/a\>/';
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
    create_dir($path_out);
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

    $dirs = array(
        '/css',
        '/js',
        '/files',
        '/session',
    );

    foreach ($dirs as $dir) {
        copy_files($path_src . $dir, $path_out . $dir);
    }

    create_dir("$path_out/session");
    create_dir("$path_out/audio");
    create_dir("$path_out/images/thumbs");
    copy_file($path_src, $path_out, 'images/background.png');

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
