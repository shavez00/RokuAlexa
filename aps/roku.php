<?php

include_once 'postConstants.php';

class Roku {
    private $rokuUri = NULL;
    private $rokuUriCacheFile = '/cfg/rokuUri.txt';
    private $listofChannelsFile = '/cfg/LIST_OF_CHANNELS.txt';
    private $rokuCachedSerialNumber = NULL;
    private $rokuSerialNumberCacheFile = '/cfg/rokuSerialNumber.txt';
    
    function __construct($serialNumber = NULL) {
        //TODO:  Add use of memcached

        //dealing with multiple Roku on the same LAN. Check the serial number.
        //get the cached serial number from disk
        $currentWorkingDirectory = getcwd();
        $this->rokuCachedSerialNumber = file_get_contents($currentWorkingDirectory . $this->rokuSerialNumberCacheFile);
        //if no serial number is passed in, then use default serial number
        if ($serialNumber == NULL) $serialNumber = $this->rokuCachedSerialNumber;
        
        //fix loop issue and improve error logging
        do {
            if(!$this->checkCachedRokuInfo($this->rokuUriCacheFile, $serialNumber)) if(!$this->getRokuUri()) $this->wakeUp();
        } while ($this->rokuUri == NULL);
    }
    
    private function checkCachedRokuInfo($rokuUriCacheFile, $serialNumber) {
        //get IP address, check cached address on disk then use UPNP to get address
        $success = FALSE;
        $currentWorkingDirectory = getcwd();
        $rokuUri = file_get_contents($currentWorkingDirectory . $rokuUriCacheFile);

        //use the cached URI to try and query the Roku using CURL
        $ch = curl_init($rokuUri);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $response = curl_exec($ch);
        curl_close($ch);
        
        if($response) $xml = new SimpleXMLElement($response);

        //if CURL returns a valid response then IP address is good for the Roku
        //Since the iP address is good then it should be saved as the URI
        if((string)$xml->device->serialNumber == $serialNumber) {
            $success = TRUE && $this->rokuUri = $rokuUri;
            file_put_contents($currentWorkingDirectory . $this->rokuSerialNumberCacheFile, $serialNumber);
        } else {
            trigger_error("Attemp to get URI of Roku from file cached on disk failed", E_USER_WARNING);
        }
        return $success;
    }
    
    public function getRokuUri($from = null, $port = null, $sockTimout = '2') {
        $success = FALSE;
        $msg = 'M-SEARCH * HTTP/1.1' . "\r\n";
        $msg .= 'HOST: 239.255.255.250:1900' . "\r\n";
        $msg .= 'MAN: "ssdp:discover"' . "\r\n";
        $msg .= 'ST: roku:ecp' . "\r\n";

        $socket = socket_create( AF_INET, SOCK_DGRAM, 0 );
        $opt_ret = socket_set_option($socket, 1, 6, TRUE );
        $send_ret = socket_sendto($socket, $msg, strlen($msg), 0, '239.255.255.250', 1900); 
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, array( 'sec'=>$sockTimout, 'usec'=>'0'));
        $response = array();
        for ($i = 0; $i <10; $i++) {
            $buf = null;
            socket_recvfrom($socket, $buf, 1024, MSG_WAITALL, $from, $port);
            if(!is_null($buf)) $response = $this->parseMSearchResponse($buf);
        }
        socket_close($socket);
        if ($response['URI']) {
            //$success = TRUE;
            $currentWorkingDirectory = getcwd();
            file_put_contents($currentWorkingDirectory . $this->rokuUriCacheFile, $response['URI']);
        } else {
            trigger_error("Attemp to get URI of Roku using UPNP failed", E_USER_WARNING);
        }
        $this->rokuUri = $response['URI'];
        return $success;
    }

    public function parseMSearchResponse($response) {
        $responseArr = explode("\r\n" , $response);
        $parsedResponse = array();
        foreach($responseArr as $row) {
            if(stripos($row, 'location') === 0) $parsedResponse['URI'] = str_ireplace('location: ', '', $row); 
        }
        return $parsedResponse;
    }
    
    private function wakeUp() {
        $success = FALSE;
        $macAddress = str_replace(':', '', MACADDRESS);
        if (!ctype_xdigit($macAddress) || strlen($macAddress) !== 12) throw new Exception('Mac address invalid, only 0-9 and a-f are allowed');
        $macAddressBinary = pack('H12', $macAddress);
        $magicPacket = str_repeat(chr(0xff), 6) . str_repeat($macAddressBinary, 16);
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1);
        $results = socket_sendto($socket, $magicPacket, strlen($magicPacket), 0, BROADCASTADDRESS, 7);
        socket_close($socket);
        if ($results !== NULL) $success = TRUE;
        return $success;
    }
    
    public function __call($name, $arguments)
    {
        $url = $this->rokuUri . $name ;
        if(!is_null($arguments)) $url = $url . "/" . $arguments[0];
        $ch = curl_init($url);
        if(in_array($name, POST_CONSTANTS)) curl_setopt ($ch, CURLOPT_POST, 1); //some roku command require a POST request
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_exec($ch);
        curl_close($ch);
    }
    
    public function getListofChannels() {
        $ch = curl_init($this->rokuUri . '/query/apps'); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        $list_of_channels = array();
        $xml = new SimpleXMLElement($result);
        foreach($xml->children() as $child) {
            $name = preg_replace("/[.]/u", "", (string)$child->{0});
            $name = strtolower($name);
            if($child["type"] == "appl") $list_of_channels[$name] = (int)$child["id"];
        }
        header_remove();
        http_response_code($code);
        header("Cache-Control: no-transform,public,max-age=300,s-maxage=900");
        header('Content-Type: application/json');
        header('Status: 200 OK');
        print_r(json_encode($list_of_channels));
    }

    public function getListofChannelsFile() {
        $ch = curl_init($this->rokuUri . '/query/apps'); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        $list_of_channels = NULL;
        $xml = new SimpleXMLElement($result);
        foreach($xml->children() as $child) {
            $name = (string)$child->{0};
            $name = strtolower($name);
            if($child["type"] == "appl") $list_of_channels = $list_of_channels . $name . "\n";
        }
        $currentWorkingDirectory = getcwd();
        file_put_contents($currentWorkingDirectory . $this->listofChannelsFile, $list_of_channels);
        var_dump($list_of_channels);
    }
}

?>