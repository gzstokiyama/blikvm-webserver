<?php

function autoLoadEntry($class){
    $try_file = APP_PATH . '/library/' . str_replace('\\', '/', $class) . '.php';
    if(file_exists($try_file)){
        require_once $try_file;
    }
}

function formatMessage($text,$foreground_color = 15,$background_color = 198){
    $setCodes = [38,5,$foreground_color,48,5,$background_color];
    $unsetCodes = [0];
    return sprintf("\033[%sm%s\033[%sm", implode(';', $setCodes), $text, implode(';', $unsetCodes));
}

function formatArgs($args,$numargs){
    if ($numargs==0) {
        $log = "";
    }elseif ($numargs==1) {
        $log  = $args[0];
    }else{
        $format = array_shift($args);
        $log = vsprintf($format, $args);
    }
    return $log;
}

function L(){
    $log = formatArgs(func_get_args(),func_num_args());
    echo formatMessage(date("[Y/m/d H:i:s]"),15,239).
        formatMessage(' [Log] ',33,233).
        $log.
    PHP_EOL;
    return true;
}

function E(){
    $log = formatArgs(func_get_args(),func_num_args());
    echo formatMessage(date("[Y/m/d H:i:s]"),15,124).
        formatMessage(' [Error] ',15,196).
        formatMessage($log,198,15).
        PHP_EOL;
    return true;
}

function W(){
    $log = formatArgs(func_get_args(),func_num_args());
    echo formatMessage(date("[Y/m/d H:i:s]"),233,214).
        formatMessage(' [Warning] ',233,226).
        formatMessage($log,227,243).
        PHP_EOL;
    return true;
}


function getMime($file_ext){
    $mimes = array(
        'c' => 'text/plain',
        'cc' => 'text/plain',
        'cpp' => 'text/plain',
        'c++' => 'text/plain',
        'dtd' => 'text/plain',
        'h' => 'text/plain',
        'log' => 'text/plain',
        'rng' => 'text/plain',
        'txt' => 'text/plain',
        'xsd' => 'text/plain',
        'avi' => 'video/avi',
        'bmp' => 'image/bmp',
        'css' => 'text/css',
        'gif' => 'image/gif',
        'htm' => 'text/html',
        'html' => 'text/html',
        'htmls' => 'text/html',
        'ico' => 'image/x-ico',
        'jpe' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'js' => 'application/x-javascript',
        'midi' => 'audio/midi',
        'mid' => 'audio/midi',
        'mod' => 'audio/mod',
        'mov' => 'movie/quicktime',
        'mp3' => 'audio/mp3',
        'mpg' => 'video/mpeg',
        'mpeg' => 'video/mpeg',
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'swf' => 'application/shockwave-flash',
        'tif' => 'image/tiff',
        'tiff' => 'image/tiff',
        'wav' => 'audio/wav',
        'xbm' => 'image/xbm',
        'xml' => 'text/xml',
        'svg' => 'image/svg+xml',
        'woff' => 'application/x-font-woff',
        'woff2' => 'application/x-font-woff',
        'eot' => 'application/vnd.ms-fontobject'
    );
    return $mimes[$file_ext] ?? 'application/octet-stream';
}



function packData($data){
    $d1 = 0;
    $special = [16,17,18,91,93];
    $dn = [];
    foreach ($data as $keycode){
        if(in_array($keycode,$special)){
            addSpecialKey($d1,$keycode);
        }else{
            addNormalKey($dn,$keycode);
        }
    }
    if(count($dn)<6){
        $pad = 6 - count($dn);
        for ($i=0;$i<$pad;$i++){
            $dn[]=0;
        }
    }
    $bin_data = pack('C*',$d1,0,$dn[0],$dn[1],$dn[2],$dn[3],$dn[4],$dn[5]);
    return $bin_data;
}

function addSpecialKey(&$current,$add){
    $shift = 0x02;
    $ctrl = 0x01;
    $alt = 0x04;
    $cmd = 0x08;

    if($add==16)
        $current = $current | $shift;
    if($add==17)
        $current = $current | $ctrl;
    if($add==18)
        $current = $current | $alt;
    if($add==91 || $add==93)
        $current = $current | $cmd;
}

function addNormalKey(&$current,$add){
    if(count($current)>6)
        return;
    $keycode = keyCodeMapping($add);
    $current[] = $keycode;
}

function keyCodeMapping($b_code){
    if($b_code>=65 && $b_code <=90){
        return $b_code-61;
    }
    if($b_code>=49 && $b_code<=57){
        return $b_code - 19;
    }
    if($b_code>=112 && $b_code<=123){
        return $b_code - 54;
    }
    $mapping = [
        48 => 0x27,
        27=> 0x29,
        192=> 0x35,
        189=> 0x2d,
        187=> 0x2e,
        8=>0x2a,
        9=>0x2b,
        219=>0x2f,
        221=>0x30,
        220=>0x31,
        20=>0x39,
        13=>0x28,
        186=>0x33,
        222=>0x34,
        188=>0x36,
        190=>0x37,
        191=>0x38,
        32=>0x2c,
        37=>0x50,
        38=>0x52,
        39=>0x4f,
        40=>0x51,
        145=>0x47,
        19=>0x48,
        45=>0x49,
        36=>0x4A,
        33=>0x4B,
        34=>0x4E,
        46=>0x4C,
        35=>0x4D,
    ];
    if(isset($mapping[$b_code]))
        return $mapping[$b_code];
    return  $b_code;
}


function translate_vertical_wheel_delta($vertical_wheel_delta){
    return $vertical_wheel_delta * -1;
}


function _scale_mouse_coordinates($relative_x, $relative_y){
    $max_hid_value = 0x7fff;
    $x = intval($relative_x * $max_hid_value);
    $y = intval($relative_y * $max_hid_value);
    return [$x,$y];
}

function send_mouse_event($buttons, $relative_x, $relative_y, $vertical_wheel_delta, $horizontal_wheel_delta){
    list($x,$y) = _scale_mouse_coordinates($relative_x,$relative_y);
    $buf = [0,0,0,0,0,0,0];
    $buf[0] = $buttons;
    $buf[1] = $x & 0xff;
    $buf[2] = ($x >> 8) & 0xff;
    $buf[3] = $y & 0xff;
    $buf[4] = ($y >> 8) & 0xff;
    $buf[5] = translate_vertical_wheel_delta($vertical_wheel_delta) & 0xff;
    $buf[6] = $horizontal_wheel_delta & 0xff;
    return pack('C*',...$buf);
}
