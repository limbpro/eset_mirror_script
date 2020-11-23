<?php

chdir(__DIR__ . "/..");

$DIRECTORIES = [
    3 => 'eset_upd',
    4 => 'eset_upd/v4',
    5 => 'eset_upd/v5',
    6 => 'eset_upd/v6',
    7 => 'eset_upd/v7',
    8 => 'eset_upd/v8',
    9 => 'eset_upd/v9',
    10 => 'eset_upd/v10',
    11 => 'eset_upd/v11',
    12 => 'eset_upd/v12',
    13 => 'eset_upd/v13',
];

$VERSION = '20191118 [Freedom for Ukraine by harmless]';

@define('DS', DIRECTORY_SEPARATOR);
@define('SELF', dirname(__DIR__) . DS);
@define('INC', SELF . "inc" . DS);
@define('CLASSES', INC . "classes" . DS);
@define('PATTERN', SELF . "patterns" . DS);
@define('CONF_FILE', SELF . "nod32ms.conf");
@define('LANGPACKS_DIR', SELF . 'langpacks' . DS);
@define('DEBUG_DIR', SELF . 'debug' . DS);
@define('KEY_FILE_VALID', 'nod_keys.valid');
@define('KEY_FILE_INVALID', 'nod_keys.invalid');
@define('LOG_FILE', 'nod32ms.log');
@define('SUCCESSFUL_TIMESTAMP', 'nod_lastupdate');
@define('LINKTEST', 'nod_linktest');
@define('DATABASES_SIZE', 'nod_databases_size');
@define('TMP_PATH', 'tmp' . DS);

$autoload = function ($class) {
    @include_once CLASSES . "$class.class.php";
};
spl_autoload_register($autoload);
