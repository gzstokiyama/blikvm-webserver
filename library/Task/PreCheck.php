<?php

namespace Task;

class PreCheck {

    private $result = true;

    private $messages = [];

    public function getResult() {
        return $this->result;
    }

    public function getMessages(){
        return $this->messages;
    }

    private function addMessage($message){
        $this->messages[] = $message;
    }

    public function outputMessages(){
        foreach ($this->messages as $index => $message){
            echo formatMessage(sprintf('[Error:%s]',$index+1),0,69);
            echo formatMessage($message);
            echo PHP_EOL;
        }
    }

    public function __construct() {
        try {
            $this->checkUserIsRoot();
            $this->checkHid();
            $this->checkIfInPhar();
        }catch (\Exception $e){
            $this->addMessage($e->getMessage());
        }
    }

    public function checkIntegrate($config){
        if(\R::exists('--skip-extra')){
            return;
        }
        if(!file_exists($config['fan_sock'])){
            $this->result = false;
            $this->addMessage(sprintf('fan control sock not found (%s)',$config['fan_sock']));
        }
        if(!file_exists($config['atx_sock'])){
            $this->result = false;
            $this->addMessage(sprintf('atx control sock not found (%s)',$config['atx_sock']));
        }
        if(!file_exists($config['fan_state'])){
            $this->result = false;
            $this->addMessage(sprintf('fan state file not found (%s)',$config['fan_state']));
        }else{
            $fan_state_len = strlen(file_get_contents($config['fan_state']));
            if($fan_state_len!=1){
                $this->result = false;
                $this->addMessage(sprintf('fan state file length error : %s',$fan_state_len));
            }
        }
        if(!file_exists($config['atx_state'])){
            $this->result = false;
            $this->addMessage(sprintf('atx state file not found (%s)',$config['atx_state']));
        }else{
            $atx_state_len = strlen(file_get_contents($config['atx_state']));
            if($atx_state_len!=1){
                $this->result = false;
                $this->addMessage(sprintf('atx state file length error : %s',$atx_state_len));
            }
        }
    }

    public function checkUserIsRoot(){
        $user = trim(`whoami`);
        if($user!='root'){
            $this->result = false;
            throw new \Exception('This program requires running using ROOT');
        }
    }

    public function checkHid(){
        if(\R::exists('--skip-usb-check')){
            return;
        }
        $keyboard = '/dev/hidg0';
        $mouse = '/dev/hidg1';
        if(!file_exists($keyboard) || !is_writable($keyboard)){
            $this->result = false;
            $this->addMessage('USB keyboard NOT detected!');
        }
        if(!file_exists($mouse) || !is_writable($mouse)){
            $this->result = false;
            $this->addMessage('USB mouse NOT detected!');
        }
    }

    public function checkIfInPhar(){
        if(strlen(\Phar::running()) > 0){ //在phar中
            $target_path = '/dev/shm/smart-kvm';
            L('Building static assets cache.');
            if(file_exists($target_path)){
                L('Cleaning previous memory cache...');
                shell_exec('rm -rf '.$target_path);
            }
            mkdir($target_path);
            if(!copy(APP_PATH .'/dist.zip',$target_path.'/dist.zip')){
                $this->result = false;
                $this->addMessage('Cannot copy dist.zip to shared memory!');
                return;
            }
            $zip = new \ZipArchive();
            $res = $zip->open($target_path.'/dist.zip');
            if ($res === TRUE) {
                $zip->extractTo($target_path);
                $zip->close();
                L('Static assets cache build success!');
            } else {
                $this->result = false;
                $this->addMessage('Zip Archive cannot OPEN !!!!');
            }
            unlink($target_path.'/dist.zip');
        }
    }

}
