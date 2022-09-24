<?php
namespace KVM;

class Extension {

    private $fan_running = false;

    private $fan_speed = 0;

    private $hdd_led = false;

    private $power_led = -1;

    private $fan_sock;
    private $fan_state;
    private $atx_sock;
    private $atx_state;

    public function __construct($paths=[]){
        if($paths){
            $this->setPaths($paths);
        }
    }

    public function __destruct() {}

    public function setPaths($paths){
        $this->fan_sock = $paths['fan_sock'];
        $this->fan_state = $paths['fan_state'];
        $this->atx_sock = $paths['atx_sock'];
        $this->atx_state = $paths['atx_state'];
    }

    public function getStatus(){
        $this->parseAtx();
        $this->parseFan();
        return [
          'fan_running'=>$this->fan_running,
          'fan_speed'=>$this->fan_speed,
          'hdd_led'=>$this->hdd_led,
          'power_led'=>$this->power_led
        ];
    }

    private function parseFan(){
        $fp = fopen($this->fan_state,'rb');
        $contents = fread($fp,1024);
        $fan = unpack('Cf',$contents);
        fclose($fp);
        $real_length = strlen($contents);
        if($real_length!=1){
            E('Fan state read length error,expect 1,got :%s',$real_length);
            return;
        }
        $fan_running = ($fan['f'] >> 7) & 0x1;
        $this->fan_speed = $fan['f'] & 0x7f;
        $this->fan_running = boolval($fan_running);
    }

    private function parseAtx(){
        $fp = fopen($this->atx_state,'rb');
        $contents = fread($fp,1024);
        $atx = unpack('Cs',$contents);
        fclose($fp);
        $real_length = strlen($contents);
        if($real_length!=1){
            E('Atx state read length error,expect 1,got :%s',$real_length);
            return;
        }
        $power_b1 = ( $atx['s'] >> 7 ) & 0x1;
        $power_b2 = ( $atx['s'] >> 6) & 0x1;
        $hdd = ($atx['s'] >> 3 ) & 0x1;
        $this->power_led = $power_b1 === 0 ? -1 : $power_b2 ;
        $this->hdd_led = boolval($hdd);
    }

    public static function generateFanCommand($is_open,$speed=127){
        if($is_open===false)
            return pack('C',0);
        return pack('C',$speed | 0x80);
    }

    public static function generateAtxCommand($cmd_number){
        return pack('C',$cmd_number);
    }

}
