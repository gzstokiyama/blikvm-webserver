<?php
$_logo = APP_PATH . '/assets/cli-logo-colorful.txt';
$_version = APP_PATH . '/assets/version.txt';

echo file_get_contents($_logo);
echo str_repeat('-',25).PHP_EOL;
echo file_get_contents($_version).PHP_EOL;
echo str_repeat('-',25).PHP_EOL;
