<?php

/**
 * Class Mirror
 */
class Mirror
{
    /**
     * @var int
     */
    static public $total_downloads = 0;

    /**
     * @var
     */
    static public $version = null;

    /**
     * @var
     */
    static public $dir = null;

    /**
     * @var
     */
    static public $mirror_dir = null;

    /**
     * @var array
     */
    static public $mirrors = array();

    /**
     * @var array
     */
    static public $key = array();

    /**
     * @var bool
     */
    static public $updated = false;

    /**
     * @var array
     */
    static private $ESET;

    /**
     *
     */
    static private function fix_time_stamp()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        $fn = Tools::ds(Config::get('LOG')['dir'], SUCCESSFUL_TIMESTAMP);
        $timestamps = [];

        if (file_exists($fn)) {
            $handle = file_get_contents($fn);
            $content = Parser::parse_line($handle, false, "/(.+:.+)\n/");

            if (isset($content) && count($content)) {
                foreach ($content as $value) {
                    $result = explode(":", $value);
                    $timestamps[$result[0]] = $result[1];
                }
            }
        }

        $timestamps[static::$version] = time();
        @unlink($fn);

        foreach ($timestamps as $key => $name)
            Log::write_to_file($fn, "$key:$name\r\n");
    }

    /**
     * @return bool
     */
    static public function test_key()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        Log::write_log(Language::t("Testing key [%s:%s]", static::$key[0], static::$key[1]), 4, static::$version);

        foreach (static::$ESET['mirror'] as $mirror) {
            $tries = 0;
            $quantity = Config::get('FIND')['errors_quantity'];

            while (++$tries <= $quantity) {
                if ($tries > 1) usleep(Config::get('CONNECTION')['timeout'] * 1000000);
                Tools::download_file(
                    [
                        CURLOPT_USERPWD => static::$key[0] . ":" . static::$key[1],
                        CURLOPT_URL => "http://" . $mirror . "/" . static::$mirror_dir . "/update.ver",
                        CURLOPT_NOBODY => 1
                    ],
                    $headers
                );
                return ($headers['http_code'] === 200 or $headers['http_code'] === 404) ? true : false;
            }
        }

        return false;
    }

    /**
     * @throws ToolsException
     */
    static public function find_best_mirrors()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        $test_mirrors = [];

        foreach (static::$ESET['mirror'] as $mirror) {
            Tools::download_file(
                [
                    CURLOPT_USERPWD => static::$key[0] . ":" . static::$key[1],
                    CURLOPT_URL => "http://" . $mirror . "/" . static::$mirror_dir . "/update.ver",
                    CURLOPT_NOBODY => 1
                ],
                $headers
            );

            if ($headers['http_code'] == 200) {
                $test_mirrors[$mirror] = round($headers['total_time'] * 1000);
                Log::write_log(Language::t("Mirror %s active", $mirror), 3, static::$version);
            } else Log::write_log(Language::t("Mirror %s inactive", $mirror), 3, static::$version);
        }
        asort($test_mirrors);

        foreach ($test_mirrors as $mirror => $time)
            static::$mirrors[] = ['host' => $mirror, 'db_version' => static::check_mirror($mirror)];
    }

    /**
     * @param $mirror
     * @return int|null
     * @throws ToolsException
     */
    static public function check_mirror($mirror)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        $new_version = null;
        $file = Tools::ds(Config::get('SCRIPT')['web_dir'], TMP_PATH, static::$mirror_dir, 'update.ver');
        Log::write_log(Language::t("Checking mirror %s with key [%s:%s]", $mirror, static::$key[0], static::$key[1]), 4, static::$version);
        static::download_update_ver($mirror);
        $new_version = static::get_DB_version($file);
        @unlink($file);

        return $new_version;
    }

    /**
     * @param $mirror
     * @throws ToolsException
     */
    static public function download_update_ver($mirror)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        $tmp_path = Tools::ds(Config::get('SCRIPT')['web_dir'], TMP_PATH, static::$mirror_dir);
        @mkdir($tmp_path, 0755, true);
        $archive = Tools::ds($tmp_path, 'update.rar');
        $extracted = Tools::ds($tmp_path, 'update.ver');
        Tools::download_file(
            [
                CURLOPT_USERPWD => static::$key[0] . ":" . static::$key[1],
                CURLOPT_URL => "http://" . "$mirror/" . static::$mirror_dir . "/update.ver",
                CURLOPT_FILE => $archive
            ],
            $headers
        );

        if (is_array($headers) and $headers['http_code'] == 200) {
            if (preg_match("/text|application\/x-wine-extension-ini/", Tools::get_file_mimetype($archive))) {
                rename($archive, $extracted);
            } else {
                Log::write_log(Language::t("Extracting file %s to %s", $archive, $tmp_path), 5, static::$version);
                Tools::extract_file(Config::get('SCRIPT')['unrar_binary'], $archive, $tmp_path);
                @unlink($archive);
                if (Config::get('SCRIPT')['debug_update'] == 1) {
                    $date = date("Y-m-d-H-i-s-") . explode('.', microtime(1))[1];
                    copy("${tmp_path}/update.ver", "${tmp_path}/update_${mirror}_${date}.ver");
                }
            }
        }
    }

    /**
     * @return array
     * @throws Exception
     * @throws ToolsException
     */
    static public function download_signature()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        static::download_update_ver(current(static::$mirrors)['host']);
        $dir = Config::get('SCRIPT')['web_dir'];
        $cur_update_ver = Tools::ds($dir, static::$mirror_dir, 'update.ver');
        $tmp_update_ver = Tools::ds($dir, TMP_PATH, static::$mirror_dir, 'update.ver');
        $content = @file_get_contents($tmp_update_ver);
        $start_time = microtime(true);
        preg_match_all('#\[\w+\][^\[]+#', $content, $matches);
        $total_size = null;
        $average_speed = null;

        if (!empty($matches)) {
            // Parse files from .ver file
            list($new_files, $total_size, $new_content) = static::parse_update_file($matches[0]);

            // Create hardlinks/copy file for empty needed files (name, size)
            list($download_files, $needed_files) = static::create_links($dir, $new_files);

            // Download files
            if (!empty($download_files)) {
                static::$updated = true;
                static::download_files($download_files);
            }

            // Delete not needed files
            foreach (glob(Tools::ds($dir, static::$dir), GLOB_ONLYDIR) as $file) {
                $del_files = static::del_files($file, $needed_files);
                if ($del_files > 0) {
                    static::$updated = true;
                    Log::write_log(Language::t("Deleted files: %s", $del_files), 3, static::$version);
                }
            }

            // Delete empty folders
            foreach (glob(Tools::ds($dir, static::$dir), GLOB_ONLYDIR) as $folder) {
                $del_folders = static::del_folders($folder);
                if ($del_folders > 0) {
                    static::$updated = true;
                    Log::write_log(Language::t("Deleted folders: %s", $del_folders), 3, static::$version);
                }
            }

            if (!file_exists(dirname($cur_update_ver))) @mkdir(dirname($cur_update_ver), 0755, true);
            @file_put_contents($cur_update_ver, $new_content);

            Log::write_log(Language::t("Total size database: %s", Tools::bytesToSize1024($total_size)), 3, static::$version);

            if (count($download_files) > 0) {
                $average_speed = round(static::$total_downloads / (microtime(true) - $start_time));
                Log::write_log(Language::t("Total downloaded: %s", Tools::bytesToSize1024(static::$total_downloads)), 3, static::$version);
                Log::write_log(Language::t("Average speed: %s/s", Tools::bytesToSize1024($average_speed)), 3, static::$version);
            }

            if (static::$updated) static::fix_time_stamp();
        } else {
            Log::write_log(Language::t("Error while parsing update.ver from %s", current(static::$mirrors)['host']), 3, static::$version);
        }
        @unlink($tmp_update_ver);
        return array($total_size, static::$total_downloads, $average_speed);
    }

    /**
     * @param $download_files
     * @throws Exception
     */
    static protected function multi_download($download_files)
    {
        $web_dir = Config::get('SCRIPT')['web_dir'];
        $CONNECTION = Config::get('CONNECTION');
        $master = curl_multi_init();
        $options = Config::getConnectionInfo();
        $options[CURLOPT_USERPWD] = static::$key[0] . ":" . static::$key[1];
        $threads = 0;
        $max_threads = !empty($CONNECTION['download_threads']) ? $CONNECTION['download_threads'] : count($download_files);
        $files = [];

        foreach ($download_files as $i => $file) {
            $ch = curl_init();
            $path = Tools::ds($web_dir, $file['file']);
            $res = dirname($path);
            if (!@file_exists($res)) @mkdir($res, 0755, true);
            $options[CURLOPT_URL] = "http://" . current(static::$mirrors)['host'] . $file['file'];
            $options[CURLOPT_FILE] = fopen($path, 'w');
            curl_setopt_array($ch, $options);
            $files[Tools::get_resource_id($ch)] = [
                'file' => $file,
                'curl' => $ch,
                'fd' => $options[CURLOPT_FILE],
                'mirror' => current(static::$mirrors)['host'],
                'path' => $path,
            ];
        }

        while (!empty($files)) {
            foreach ($files as $i => $file) {
                curl_multi_add_handle($master, $file['curl']);
                $threads++;
                Log::write_log(Language::t("Running %s: threads %s in foreach", __METHOD__, $threads), 5, static::$version);

                while (($threads >= $max_threads and $CONNECTION['download_threads'] != 0)) {
                    Log::write_log(Language::t("Running %s: threads %s in while", __METHOD__, $threads), 5, static::$version);

                    usleep(50000);
                    curl_multi_exec($master, $running);

                    if (($select = curl_multi_select($master)) < 1) continue;

                    do {
                        $status = curl_multi_exec($master, $running);
                        usleep(10000);
                    } while ($status == CURLM_CALL_MULTI_PERFORM || $running);

                    while ($done = curl_multi_info_read($master)) {
                        $ch = $done['handle'];
                        $id = Tools::get_resource_id($ch);
                        $info = curl_getinfo($ch);
                        $host = $files[$id]['mirror'];
                        if ($info['http_code'] == 200 && $file['file']['size'] == $info['download_content_length']) {
                            @fclose($files[$id]['fd']);
                            unset($files[$id]);
                            Log::write_log(
                                Language::t("From %s downloaded %s [%s] [%s/s]", $host, basename($info['url']),
                                    Tools::bytesToSize1024($info['download_content_length']),
                                    Tools::bytesToSize1024($info['speed_download'])),
                                3,
                                static::$version
                            );
                            static::$total_downloads += $info['download_content_length'];
                            curl_multi_remove_handle($master, $ch);
                            curl_close($ch);
                            $threads--;
                        } else {
                            Log::write_log(Language::t("Error download url %s", $info['url']), 3, static::$version);
                            $f = $files[$id];

                            @fclose($files[$id]['fd']);
                            unlink($files[$id]['file']['path']);
                            unset($files[$id]);
                            curl_multi_remove_handle($master, $ch);
                            curl_close($ch);

                            if (next(static::$mirrors)) {
                                Log::write_log(Language::t("Try next mirror %s", current(static::$mirrors)['host']), 3, static::$version);
                                $f['mirror'] = current(static::$mirrors);
                                $ch = curl_init();
                                $options[CURLOPT_URL] = "http://" . $f['mirror'] . $f['file']['file'];
                                $options[CURLOPT_FILE] = fopen($f['path'], 'w');
                                curl_setopt_array($ch, $options);
                                $files[Tools::get_resource_id($ch)] = [
                                    'file' => $f['file'],
                                    'curl' => $ch,
                                    'fd' => &$options[CURLOPT_FILE],
                                    'mirror' => $f['mirror'],
                                    'path' => $f['path'],
                                ];
                            } else {
                                Log::write_log(Language::t("All mirrors is down!"), 3, static::$version);
                            }
                            $threads--;
                            reset(static::$mirrors);
                        }
                    }
                }
            }

            do {
                Log::write_log(Language::t("Running %s: threads %s in do", __METHOD__, $threads), 5, static::$version);

                usleep(50000);
                curl_multi_exec($master, $running);

                if (($select = curl_multi_select($master)) < 1) continue;

                do {
                    $status = curl_multi_exec($master, $running);
                    Log::write_log(Language::t("Threads %s in do doing do status=%s running=%s", $threads, $status, $running), 5, static::$version);
                    usleep(10000);
                } while ($status == CURLM_CALL_MULTI_PERFORM || $running);

                while ($done = curl_multi_info_read($master)) {
                    Log::write_log(Language::t("Threads %s in do doing while"), 5, static::$version);
                    $ch = $done['handle'];
                    $id = Tools::get_resource_id($ch);
                    $info = curl_getinfo($ch);
                    $file = $files[$id];
                    $host = $file['mirror'];
                    if ($info['http_code'] == 200 && $file['file']['size'] == $info['download_content_length']) {
                        @fclose($file['fd']);
                        unset($files[$id]);
                        Log::write_log(
                            Language::t("From %s downloaded %s [%s] [%s/s]", $host, basename($info['url']),
                                Tools::bytesToSize1024($info['download_content_length']),
                                Tools::bytesToSize1024($info['speed_download'])),
                            3,
                            static::$version
                        );
                        static::$total_downloads += $info['download_content_length'];
                        curl_multi_remove_handle($master, $ch);
                        curl_close($ch);
                        $threads--;
                    } else {
                        Log::write_log(Language::t("Error download url %s", $info['url']), 3, static::$version);
                        $f = $files[$id];

                        @fclose($files[$id]['fd']);
                        unlink($files[$id]['file']['path']);
                        unset($files[$id]);
                        curl_multi_remove_handle($master, $ch);
                        curl_close($ch);

                        if (next(static::$mirrors)) {
                            Log::write_log(Language::t("Try next mirror %s", current(static::$mirrors)['host']), 3, static::$version);
                            $f['mirror'] = current(static::$mirrors);
                            $ch = curl_init();
                            $options[CURLOPT_URL] = "http://" . $f['mirror'] . $f['file']['file'];
                            $options[CURLOPT_FILE] = fopen($f['path'], 'w');
                            curl_setopt_array($ch, $options);
                            $files[Tools::get_resource_id($ch)] = [
                                'file' => $f['file'],
                                'curl' => $ch,
                                'fd' => &$options[CURLOPT_FILE],
                                'mirror' => $f['mirror'],
                                'path' => $f['path'],
                            ];
                        } else {
                            Log::write_log(Language::t("All mirrors is down!"), 3, static::$version);
                        }
                        $threads--;
                        reset(static::$mirrors);
                    }
                }
            } while (!empty($files));
        }

        curl_multi_close($master);
    }

    /**
     * @param $download_files
     */
    static protected function single_download($download_files)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        $web_dir = Config::get('SCRIPT')['web_dir'];

        foreach ($download_files as $file) {
            foreach (static::$mirrors as $id => $mirror) {
                $time = microtime(true);
                Log::write_log(Language::t("Trying download file %s from %s", basename($file['file']), $mirror['host']), 3, static::$version);
                $out = Tools::ds($web_dir, $file['file']);
                Tools::download_file(
                    [
                        CURLOPT_USERPWD => static::$key[0] . ":" . static::$key[1],
                        CURLOPT_URL => "http://" . $mirror['host'] . $file['file'],
                        CURLOPT_FILE => $out
                    ],
                    $header
                );

                if (is_array($header) and $header['http_code'] == 200 and $header['size_download'] == $file['size']) {
                    static::$total_downloads += $header['size_download'];
                    Log::write_log(Language::t("From %s downloaded %s [%s] [%s/s]", $mirror['host'], basename($file['file']),
                        Tools::bytesToSize1024($header['size_download']),
                        Tools::bytesToSize1024($header['size_download'] / (microtime(true) - $time))),
                        3,
                        static::$version
                    );
                    static::$total_downloads += $header['size_download'];
                    break;
                } else {
                    @unlink($out);
                }
            }
        }
    }

    /**
     * @param $download_files
     * @throws Exception
     */
    static protected function download($download_files)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);

        switch (function_exists('curl_multi_init')) {
            case true:
                /*              static::multi_download($download_files);
                              break;
                */
            case false:
            default:
                static::single_download($download_files);
                break;
        }
    }

    /**
     * @param $matches
     * @return array
     */
    static protected function parse_update_file($matches)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        $new_content = '';
        $new_files = array();
        $total_size = 0;

        foreach ($matches as $container) {

            $parsed_container = parse_ini_string(
                preg_replace(
                    "/version=(.*?)\n/i",
                    "version=\"\${1}\"\n",
                    str_replace(
                        "\r\n",
                        "\n",
                        $container
                    )
                ),
                true);
            $output = array_shift($parsed_container);

            if (intval(static::$version) < 10) {
                if (empty($output['file']) or empty($output['size']) or empty($output['date']) or
                    (!empty($output['language']) and !in_array($output['language'], static::$ESET['lang'])) or
                    (static::$ESET['x32'] != 1 and preg_match("/32|86/", $output['platform'])) or
                    (static::$ESET['x64'] != 1 and preg_match("/64/", $output['platform'])) or
                    (static::$ESET['ess'] != 1 and preg_match("/ess/", $output['type']))
                )
                    continue;
            } else {
                if (empty($output['file']) or empty($output['size']) or
                    (static::$ESET['x32'] != 1 and preg_match("/32|86/", $output['platform'])) or
                    (static::$ESET['x64'] != 1 and preg_match("/64/", $output['platform'])) or
                    (static::$ESET['ess'] != 1 and preg_match("/ess/", $output['type']))
                )
                    continue;
            }

            $new_files[] = $output;
            $total_size += $output['size'];
            $new_content .= $container;
        }

        return array($new_files, $total_size, $new_content);
    }

    /**
     * @param $download_files
     * @throws Exception
     * @throws ToolsException
     */
    static protected function download_files($download_files)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        shuffle($download_files);
        Log::write_log(Language::t("Downloading %d files", count($download_files)), 3, static::$version);

        if (static::check_mirror(current(static::$mirrors)['host']) != null) static::download($download_files);
    }

    /**
     * @param $version
     * @param $dir
     */
    static public function init($version, $dir)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, $version);
        register_shutdown_function(array('Mirror', 'destruct'));
        static::$total_downloads = 0;
        static::$version = $version;
        static::$dir = 'v' . static::$version . '-rel-*';
        static::$mirror_dir = $dir;
        static::$updated = false;
        static::$ESET = Config::get('ESET');
        Log::write_log(Language::t("Mirror initiliazed with dir=%s, mirror_dir=%s", static::$dir, static::$mirror_dir), 5, static::$version);
    }

    /**
     * @param $key
     */
    static public function set_key($key)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        static::$key = $key;
    }

    /**
     *
     */
    static public function destruct()
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        static::$total_downloads = 0;
        static::$version = null;
        static::$dir = null;
        static::$mirror_dir = null;
        static::$mirrors = array();
        static::$key = array();
        static::$updated = false;
    }

    /**
     * @param $folder
     * @return int
     */
    static public function del_folders($folder)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        $del_folders_count = 0;
        $directory = new RecursiveDirectoryIterator($folder);

        foreach ($directory as $fileObject) {
            $test_folder = $fileObject->getPathname();

            if (count(glob(Tools::ds($test_folder, '*'))) === 0) {
                @rmdir($test_folder);
                $del_folders_count++;
            }
        }

        if (count(glob(Tools::ds($folder, '*'))) === 0) {
            @rmdir($folder);
            $del_folders_count++;
        }

        return $del_folders_count;
    }

    /**
     * @param $file
     * @param $needed_files
     * @return int
     */
    static public function del_files($file, $needed_files)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        $del_files_count = 0;
        $directory = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($file), RecursiveIteratorIterator::SELF_FIRST);

        foreach ($directory as $fileObject) {
            if (!$fileObject->isDir()) {
                $test_file = $fileObject->getPathname();

                if (!in_array($test_file, $needed_files)) {
                    @unlink($test_file);
                    $del_files_count++;
                }
            }
        }

        return $del_files_count;
    }

    /**
     * @param $dir
     * @param $new_files
     * @return array
     */
    static public function create_links($dir, $new_files)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);
        $old_files = [];
        $needed_files = [];
        $download_files = [];
        $iterator = new RegexIterator(
            new RecursiveIteratorIterator(
                new RecursiveRegexIterator(
                    new RecursiveDirectoryIterator($dir),
                    '/v\d+-(' . static::$ESET['filter'] . ')/i'
                )
            ),
            '/\.nup$/i'
        );

        foreach ($iterator as $file) {
            $old_files[] = $file->getPathname();
        }

        foreach ($new_files as $array) {
            $path = Tools::ds($dir, $array['file']);
            $needed_files[] = $path;

            if (file_exists($path) && !Tools::compare_files(@stat($path), $array)) unlink($path);

            if (!file_exists($path)) {
                $results = preg_grep('/' . basename($array['file']) . '$/', $old_files);

                if (!empty($results)) {
                    foreach ($results as $result) {
                        if (Tools::compare_files(@stat($result), $array)) {
                            $res = dirname($path);

                            if (!file_exists($res)) mkdir($res, 0755, true);

                            switch (Config::get('create_hard_links')) {
                                case 'link':
                                    link($result, $path);
                                    Log::write_log(Language::t("Created hard link for %s", basename($array['file'])), 3, static::$version);
                                    break;
                                case 'fsutil':
                                    shell_exec(sprintf("fsutil hardlink create %s %s", $path, $result));
                                    Log::write_log(Language::t("Created hard link for %s", basename($array['file'])), 3, static::$version);
                                    break;
                                case 'copy':
                                default:
                                    copy($result, $path);
                                    Log::write_log(Language::t("Copied file %s", basename($array['file'])), 3, static::$version);
                                    break;
                            }

                            static::$updated = true;

                            break;
                        }
                    }
                    if (!file_exists($path) && !array_search($array['file'], $download_files)) $download_files[] = $array;
                } else $download_files[] = $array;
            }
        }
        return [$download_files, $needed_files];
    }

    /**
     * @param $file
     * @return int|null
     */
    static public function get_DB_version($file)
    {
        Log::write_log(Language::t("Running %s", __METHOD__), 5, static::$version);

        if (!file_exists($file)) return null;

        $content = file_get_contents($file);
        $upd = Parser::parse_line($content, "versionid");
        $max = 0;

        if (isset($upd) && preg_match('/(' . static::$ESET['filter'] . ')/', $content))
            foreach ($upd as $key) $max = $max < intval($key) ? $key : $max;

        return $max;
    }
}
