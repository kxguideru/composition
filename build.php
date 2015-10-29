<?php

define('OUTPUT_WRAP', 80);
define('THUMB_WIDTH', 150);
define('THUMB_HEIGHT', 150);

define('ROOT_PATH', __DIR__);
define('OUTPUT_PATH', ROOT_PATH . '/www');
define('SRC_PATH', ROOT_PATH . '/src');

define('TEMPLATE', SRC_PATH . '/template.html');
define('INDEX', OUTPUT_PATH . '/index.html');

define('MSG_OPEN_IMAGE', 'Показать изображение');

function cleanup($output_path, $index) {
    $images = $output_path . '/images';
    $thumbs = $images . '/thumbs';

    if (!file_exists($images)) {
        mkdir($images);
    }

    if (!file_exists($thumbs)) {
        mkdir($thumbs);
    }

    cleanup_dir($images);
    cleanup_dir($thumbs);

    if (file_exists($index)) {
        unlink($index);
    }

    $css = $output_path . '/css/styles.css';

    if (file_exists($css)) {
        unlink($css);
    }
}

function cleanup_dir($path) {
    $files = glob("$path/*");

    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
}

function copy_image($source_path, $dest_path, $filename) {
    $source = $source_path . '/' . $filename;
    $dest = $dest_path . '/' . $filename;
    $thumb = $dest_path . '/thumbs/thumb-' . $filename;

    if (!file_exists($dest) || !file_exists($thumb) ||
            filemtime($dest) < filemtime($source) ||
            filemtime($thumb) < filemtime($source)) {
        copy($source, $dest);
        $imagick = new \Imagick(realpath($dest));
        $imagick->setbackgroundcolor('rgb(64, 64, 64)');
        $imagick->thumbnailImage(THUMB_WIDTH, THUMB_HEIGHT, true);
        $blob = $imagick->getimageblob();
        file_put_contents($thumb, $blob);
    }
}

function image_callback($matches) {
    $filename = $matches[1];
    $image_name = $matches[2];
    copy_image(SRC_PATH . '/images', OUTPUT_PATH . '/images', $filename);
    $alt_text = ucwords(str_replace('-', ' ', $image_name));

    $real_url = 'images/' . $filename;
    $thumb_image = '<img src="images/thumbs/thumb-' . $filename . '" alt="' . $alt_text . '">';

    $lightbox = "<a href=\"$real_url\" data-toggle=\"lightbox\" data-title=\"Изображение: $filename\" class=\"img-thumbnail\">$thumb_image</a>";
    return $lightbox;
}

function template_callback($matches) {
    $filename = $matches[1];
    $html = file_get_contents('src/' . $filename);
    $image_pattern = '/\<img src=\"images\/(([^\.]+)\.png)\"[^\>]+\>/';
    return preg_replace_callback($image_pattern, "image_callback", $html);
}

function simple_copy($path_src, $path_out, $relative) {
    $out = $path_out . '/' . $relative;
    $src = $path_src . '/' . $relative;
    
    if (!file_exists($out) || filemtime($out) < filemtime($src)) {
        copy($src, $out);
    }
}

function build($template_file, $output_file) {
    $path_out = dirname($output_file);
    $path_src = dirname($template_file);
    $out_css_path = $path_out . '/css';
        
    if (!file_exists($out_css_path)) {
        mkdir($out_css_path);
    }
    
    simple_copy($path_src, $path_out, 'css/style.css');
    simple_copy($path_src, $path_out, 'images/background.png');

    $template = file_get_contents($template_file);
    $html = preg_replace_callback('/\{\{([^}]+)\}\}/', "template_callback", $template);
    file_put_contents($output_file, $html);
}

function main($argc, $argv) {
    if ($argc > 1) {
        $job = $argv[1];

        switch ($job) {
            case "clean":
                echo "Cleaning up...\n";
                cleanup(OUTPUT_PATH, INDEX);
                return;

            case "build":
                echo "Building...\n";
                build(TEMPLATE, INDEX);
                return;

            default:
        }
    }

    echo "Usage: php build.php {clean|build}\n\n";
}

main($argc, $argv);
