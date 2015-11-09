<?php

define('THUMB_WIDTH', 150);
define('THUMB_HEIGHT', 150);

define('ROOT_PATH', __DIR__);
define('OUTPUT_PATH', ROOT_PATH . '/www');
define('SRC_PATH', ROOT_PATH . '/src');
define('SESSION_PATH', ROOT_PATH . '/session-files');

define('SESSION_REPO', 'https://github.com/kxguideru/composition-files/');

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
 * Get git branch names for repo path
 *
 * @param $path
 * @return array
 */
function get_branch_list($path)
{
    chdir($path);
    $output = $branches = array();
    exec('git branch -r', $output);

    foreach ($output as $string) {
        $trim = trim($string);
        $parts = explode('/', $trim);

        if ($parts[1] != 'HEAD' && $parts[1] != 'master') {
            $branches[$parts[1]] = $trim;
        }
    }

    return $branches;
}

/**
 * Create session files from git branches
 *
 * @param $output_path Zip path
 */
function create_session_files($output_path)
{
    $path = SESSION_PATH;

    if (!file_exists($path)) {
        exec('git clone ' . SESSION_REPO . ' ' . $path);
    } else {
        chdir($path);
        exec('git pull');
    }

    $branches = get_branch_list($path);
    chdir($path);

    foreach ($branches as $name => $branch) {
        if (substr($name, 0, 4) != "HEAD") {
            exec("git archive -o $output_path/$name.zip $branch");
        }
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
        '/fonts',
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
function is_dir_empty($dir)
{
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
 * @return array Thumbnail dimensions
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
        $lame = 'lame';
        $env = getenv('OPENSHIFT_REPO_DIR');

        if (file_exists($env . '/srv/lame/bin/lame')) {
            $lame = $env . '/srv/lame/bin/lame';
        }

        $command = "$lame $options $original_file $mp3_file";
        exec($command);
    }
}

/**
 * Session/mp3 link template
 *
 * @param $has_audio
 * @param $has_session
 * @param $name
 * @return string
 */
function insert_files_template($has_audio, $has_session, $name)
{
    $session_template = "Скачать <a href=\"session/{{NAME}}.zip\">состояние сессии</a>.";
    $audio_template = "<audio controls>\n" .
        "<source src=\"audio/{{NAME}}.mp3\" type=\"audio/mpeg\" />\n" .
        "<a href=\"audio/{{NAME}}.mp3\">скачать</a>\n" .
        "Для воспроизведения аудио требуется браузер с поддержкой HTML5.\n" .
        "</audio></p>";

    if ($has_audio || $has_session) {
        $session = $audio = "";

        if ($has_audio) {
            $audio = str_replace('{{NAME}}', $name, $audio_template);
            create_mp3(SRC_PATH . "/audio/$name.wav", OUTPUT_PATH . "/audio/$name.mp3");
        }

        if ($has_session) {
            $session = str_replace('{{NAME}}', $name, $session_template);
        }

        $html = "<p><div style=\"float: right;\"><em>$session</em></div>";

        if ($audio) {
            $html .= "Промежуточный рендер:</p>$audio";
        } else {
            $html .= '&nbsp;</p>';
        }

        return $html;
    }
}

/**
 * Insert session/mp3 links
 *
 * @param $text
 * @param $name
 * @return mixed|string
 */
function insert_files($text, $name)
{
    $has_audio = file_exists(SRC_PATH . "/audio/$name.wav");
    $has_session = file_exists(OUTPUT_PATH . "/session/$name.zip");

    if ($has_audio || $has_session) {
        return $text . insert_files_template($has_audio, $has_session, $name);
    } else {
        $has_audio = file_exists(SRC_PATH . "/audio/$name-1.wav");
        $has_session = file_exists(OUTPUT_PATH . "/session/$name-1.zip");

        if ($has_audio || $has_session) {
            preg_match_all("/<(h\d*)>[^<]*/i", $text, $matches, PREG_SET_ORDER);
            $headings = array();
            $counter = 0;

            foreach ($matches as $val) {
                $type = $val[1];

                if (strtolower($type) == 'h4') {
                    $headings[$counter++] = $val[0];
                }
            }

            for ($i = 1; $i < $counter; $i++) {
                $heading_name = $name . '-' . $i;
                $has_audio = file_exists(SRC_PATH . "/audio/$heading_name.wav");
                $has_session = file_exists(OUTPUT_PATH . "/session/$heading_name.zip");
                $insert = insert_files_template($has_audio, $has_session, $heading_name);
                $text = str_replace($headings[$i], $insert . $headings[$i], $text);
            }

            $has_audio = file_exists(SRC_PATH . "/audio/$name-$counter.wav");
            $has_session = file_exists(OUTPUT_PATH . "/session/$name-$counter.zip");
            $insert = insert_files_template($has_audio, $has_session, "$name-$counter");
            $text .= $insert;
        }
    }

    return $text;
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
 * Callback for template pattern, insert file
 *
 * @param $matches
 * @return mixed
 */
function template_callback($matches)
{
    $image_pattern = '/\<img\s+src=\"images\/(([^\.]+)\.png)\"[^\>]+\>/';
    $filename = $matches[1];
    $html = file_get_contents(SRC_PATH . '/' . $filename);
    $html = preg_replace_callback($image_pattern, "image_callback", $html);
    $html = insert_files($html, basename($filename, '.html'));
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
function build($path_src, $path_out, $is_fast = false)
{
    $template_file = $path_src . '/template.html';
    $index_file = $path_out . '/index.html';

    $dirs = array(
        '/css',
        '/js',
        '/files',
        '/fonts',
    );

    foreach ($dirs as $dir) {
        copy_files($path_src . $dir, $path_out . $dir);
    }

    create_dir("$path_out/session");

    if (!$is_fast) {
        create_session_files("$path_out/session");
    }

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

            case "fastbuild":
                echo "Fast building...\n";
                build(SRC_PATH, OUTPUT_PATH, true);
                return;

            default:
        }
    }

    echo "Usage: php build.php {clean|build|fastbuild}\n\n";
}

main($argc, $argv);
