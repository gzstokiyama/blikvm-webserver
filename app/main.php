<?php

class main {

    private $config;

    private $websocket_server;

    public function __construct($ini_file_path) {
        L('Program start');
        $ini = parse_ini_file($ini_file_path, true);
        if ($ini === false) {
            throw new Exception('Error Reading Config file', 1);
        }
        $this->config = $ini;
    }

    public function __destruct() {}

    public function bootstrap(){
        L('Bootstrapping');
        $phar = \Phar::running(false);
        if(strlen($phar) > 0){
            define('RES_PATH','/dev/shm/smart-kvm');
            L('You are running Release Version');

            $custom_ini_path = dirname($phar).'/config.ini';
            if(file_exists($custom_ini_path)){
                L('Loading user custom configure file...');
                $user_config = parse_ini_file($custom_ini_path,true);
                if($user_config===false || !isset($user_config['integrate'])){
                    throw new Exception('user custom config file error!,EXIT !!!!!!');
                }else{
                    $this->config['integrate'] = $user_config['integrate'];
                }
            }else{
                W('Custom configure file not found , skip!');
            }
        }else{
            define('RES_PATH',APP_PATH);
            W('You are running Dev Version , loading custom config file is NOT supported ! ');
        }
        if(!$this->preCheck()) {
            E('Bootstrap failed!');
            throw new Exception('Program EXIT!',-1);
        }
        return $this;
    }

    public function init() {
        L('Initializing');
        $this->websocket_server = new \Swoole\WebSocket\Server('0.0.0.0', $this->config['server']['listen'], SWOOLE_PROCESS, SWOOLE_SOCK_TCP);
        $this->websocket_server->set($this->config['settings']);
        $this->websocket_server->on('Start', array($this, 'OnStart'));
        $this->websocket_server->on('ManagerStart', array($this, 'OnManagerStart'));
        $this->websocket_server->on('WorkerStart', array($this, 'OnWorkerStart'));
        $this->websocket_server->on('Request', array($this, 'OnRequest'));
        $this->websocket_server->on('Message',array($this, 'onMessage'));
        $this->websocket_server->on('Task', array($this, 'OnTask'));
        $this->websocket_server->on('Finish', array($this, 'OnFinish'));
        $this->websocket_server->on('Close', array($this, 'OnClose'));
        return $this;
    }

    public function preCheck(){
        $check = new \Task\PreCheck();
        $check->checkIntegrate($this->config['integrate']);
        $result = $check->getResult();
        if(!$result){
            $check->outputMessages();
        }
        return $result;
    }

    public function run() {
        $this->websocket_server->start();
    }

    public function OnStart(\Swoole\WebSocket\Server $server) {
        swoole_set_process_name($this->config['server']['name'].':master');
        L("Server Start Success");
    }

    public function OnManagerStart(\Swoole\WebSocket\Server $server) {
        swoole_set_process_name($this->config['server']['name'].':manager');
    }

    public function OnWorkerStart(\Swoole\WebSocket\Server $server, $worker_id) {
        if ($worker_id >= $server->setting['worker_num']) {
            swoole_set_process_name($this->config['server']['name'].':task worker ' . $worker_id);
        } else {
            swoole_set_process_name($this->config['server']['name'].':event worker ' . $worker_id);
        }

        if($worker_id==$server->setting['worker_num']){
            if(!\R::exists('--disable-inotify')){
                $events = [
                    IN_ACCESS => 'File Accessed',
                    IN_MODIFY => 'File Modified',
                    IN_ATTRIB => 'File Metadata Modified',
                    IN_CLOSE_WRITE => 'File Closed, Opened for Writing',
                    IN_CLOSE_NOWRITE => 'File Closed, Opened for Read',
                    IN_OPEN => 'File Opened',
                    IN_MOVED_FROM => 'File Moved Out',
                    IN_CREATE => 'File Created',
                    IN_DELETE => 'File Deleted'
                ];
                $fd = inotify_init();
                stream_set_blocking($fd, 1);
                $watch = inotify_add_watch($fd, dirname($this->config['integrate']['fan_state']), IN_MODIFY | IN_CREATE | IN_DELETE );
                $ext = new \KVM\Extension($this->config['integrate']);

                while ($event_list = inotify_read($fd)) {
                    L('inotify received events,reading fan and atx state!');
                    foreach ($event_list as $arr) {
                        $ev_mask = $arr['mask'];
                        $ev_file = $arr['name'];
                        if (isset($events[$ev_mask])) {
                            L('[inotify] %s, Filename: %s',$events[$ev_mask],$ev_file);
                        } else {
                            L('[inotify] Event code: %s, Filename: %s',$arr['mask'],$ev_file);
                        }
                    }
                    $status = $ext->getStatus();
                    foreach ($server->connections as $connection){
                        if($server->isEstablished($connection)){
                            $server->push($connection,json_encode($status));
                        }
                    }
                }


            }
        }
    }

    public function OnRequest(\Swoole\Http\Request $request, \Swoole\Http\Response $response) {
        $request_uri = $request->server['request_uri'];

        $origin = $request->header['origin'] ?? '*';
        $response->header('Access-Control-Allow-Origin', $origin);
        $response->header('Access-Control-Allow-Methods', 'POST,GET,OPTIONS');
        $response->header('Access-Control-Allow-Credentials', 'true');
        $response->header('Access-Control-Max-Age', '86400');
        $response->header('Access-Control-Allow-Headers', 'Origin, Pragma, Content-Type, token, X-Requested-With');
        if ($request->server['request_method'] == 'OPTIONS') {
            $response->status(204, 'No Content');
            $response->end();
            return;
        }

        if ($request_uri == '/favicon.ico') {
            $response->header('Content-Type', 'image/x-icon');
            $response->end(file_get_contents(RES_PATH . '/dist/favicon.ico'));
            return;
        }
        if (substr($request_uri, 0, 15) == '/built-package/') {
            if (file_exists(RES_PATH . '/dist' . $request_uri)) {
                $mime = getMime(pathinfo(RES_PATH . $request_uri, PATHINFO_EXTENSION));
                $response->header('Content-Type', $mime);
                $response->end(file_get_contents(RES_PATH . '/dist' . $request_uri));
            } else {
                $response->end('404 not found');
            }
            return;
        }

        if ($request_uri == '/status_json') {
            $stats = $this->websocket_server->stats();
            $_return = ['status' => 1, 'info' => 'success', 'data' => $stats];
            $response->header("Content-Type", "application/json");
            $response->end(json_encode($_return));
            return;
        }


        if (file_exists(RES_PATH . '/dist/index.html')) {
            $response->header('Content-Type', 'text/html');
            $response->end(file_get_contents(RES_PATH . '/dist/index.html'));
        } else {
            $response->end('Error:index page not found');
        }
    }

    public function onMessage(\Swoole\WebSocket\Server $server, \Swoole\WebSocket\Frame $frame) {
        $data = json_decode($frame->data,true);
        if(!is_array($data))
            return;
        if(isset($data['k'])){
            $packed_data = packData($data['k']);
            file_put_contents('/dev/hidg0',$packed_data);
            return;
        }

        if(isset($data['m'])){
            $mouse_data = send_mouse_event(
                $data['m']['buttons'],
                $data['m']['relativeX'],
                $data['m']['relativeY'],
                $data['m']['verticalWheelDelta'],
                $data['m']['horizontalWheelDelta']
            );
            file_put_contents('/dev/hidg1',$mouse_data);
            return;
        }

        if(isset($data['ext'])){
            if(\R::exists('--skip-extra')){
                return;
            }
            $ext = new \KVM\Extension($this->config['integrate']);
            $status = $ext->getStatus();
            $server->push($frame->fd, json_encode($status));
            return;
        }

        if(isset($data['fan'])){
            if($data['fan']==0){
                $cmd = \KVM\Extension::generateFanCommand(false);
            }else{
                $cmd = \KVM\Extension::generateFanCommand(true,$data['fan']);
            }
            $socket = socket_create(AF_UNIX, SOCK_DGRAM, 0);
            if(!$socket)
                E('Error creating unix socket at %s',__LINE__);
            socket_sendto($socket, $cmd, 1, 0, $this->config['integrate']['fan_sock'], 0);
            socket_close($socket);
            return;
        }

        if(isset($data['atx'])){
            $cmd = \KVM\Extension::generateAtxCommand($data['atx']);
            $socket = socket_create(AF_UNIX, SOCK_DGRAM, 0);
            if(!$socket)
                E('Error creating unix socket at %s',__LINE__);
            socket_sendto($socket, $cmd, 1, 0, $this->config['integrate']['atx_sock'], 0);
            socket_close($socket);
            return;
        }

    }

    public function OnTask(\Swoole\WebSocket\Server $server, $task_id, $from_id, $data) {}

    public function OnFinish(\Swoole\WebSocket\Server $server, $task_id, $data) {}

    public function OnClose(\Swoole\WebSocket\Server $server, $fd) {}
}
