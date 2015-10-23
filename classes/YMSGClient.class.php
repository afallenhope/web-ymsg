<?php
// if debug show errors hide notices though.
if(DEBUG)
  error_reporting(E_ALL & ~E_NOTICE);


header('Content-Type: text/html; charset=UTF-8');

mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_http_input('UTF-8');
mb_regex_encoding('UTF-8'); 

ob_start(); // start output buffering

class YMSGClient {  
  public $yid;
  public $ypass;
  
  public $cookie;
  private $socket;
  private $server;
  private $port;
  private $delimeter;
  private $notifier;
  private $msg;
  
  public function __construct($server,$port) {
      $this->server = gethostbyname($server);
      $this->port = intval($port);
      $this->delimeter = chr(0xC0).chr(0x80);
      
      // setup our events      
      Events::bind('ymsg.data.sent',function($args = array()){ $this->onSentData($args);});
      Events::bind('ymsg.data.recv',function($args = array()){ $this->onRecvData($args);});
      Events::bind('ymsg.cookie.got',function($args = array()){ $this->gotCookie($args);});
      Events::bind('ymsg.cookie.error',function($args = array()){ $this->onError($args);});
      Events::bind('ymsg.connected',function($args = array()){ $this->onConnect($args);});
      Events::bind('ymsg.message.received',function($args = array()){$this->onMessage($args);});
      Events::bind('ymsg.disconnected',function($args = array()){$this->onDisconnect($args);});
  }  
  
  /**
   * Connect - connect to the server
   * Connect to the specified server using login and password
   * @param {string} $yid - yahoo id
   * @param {string} $ypass - yahoo password
   */
  public function Connect($yid, $ypass) {
      printf("Attempting to connect to %s<br>",$this->server);
      $this->yid = $yid;
      //the following is unnecessary I suppose, it's just so if the object get print_r the password isn't plain text 
      $b64 = base64_encode($ypass);
      $y64 = $this->Yahoo64($b64);
      $this->ypass = $y64;      
      
      // open connection to server
      $sock = fsockopen(sprintf("tcp://%s",$this->server) ,$this->port, $errNo, $errStr,60);      
      
      // if we didn't connect
      if(! $sock) {
        
        // trigger our event
        Events::trigger('ymsg.disconnected',array('message'=>$errStr));
        throw new Exception(sprintf("Error occurred while connecting: (%d) %s",$errNo,$errStr));
      } 
      
      // otherwise assign our socket
      $this->socket = $sock;
      // raise the event
      Events::trigger('ymsg.connected',$sock);
  }
  
  
  /**
   * gotCookie - event when our cookie has been received
   * @param {array} $event - contains an array with the cookie and the encrypted data (hashed password)
   */
  public function gotCookie($event) {
    
    // collect the cookie from the event data
    $cookie = $event['cookie'];
    
    // assign the cookie
    $this->cookie = $cookie;
    
    // alert the user
    printf("Got cookie for: %s\n<br> (%s)<br>",$this->yid,nl2br(print_r($this->cookie,1)));
    
    // send our login packet.
    $lpacket = new LoginPacket('84');    
    $lpacket->setData(array('yid'=>$this->yid,'pwd'=>$event['encdata'], 'cookie'=>$cookie));
    $tmp = $lpacket;        
    
    // wrap it in a try catch
    try{
      // if we were able to write to the socket, trigger the event that success wrote to server
      if(fwrite($this->socket,$lpacket))
      Events::trigger('ymsg.data.sent', $lpacket);
    } catch(Exception $ex) {
      
      // otherwise if we got an exception trigger disconnected 
      Events::trigger('ymsg.disconnected', array('message'=>$ex->getMessage()));
      fclose($this->socket);
    }
  }
  
  /**
   * onConnect - event when connected
   * @param {array} - returns the socket
   */
  public function onConnect($event) {
    // print it to the user we've connected.
    printf("Connected to the server as %s<br>", $this->yid);        
    
    // begin the authentication 
    $auth = new Auth();
    $auth->setData(array('yid'=>$this->yid));       
    $tmp = $auth;
    
    try {      
      if(fwrite($this->socket,$tmp)) {
        Events::trigger('ymsg.data.sent',$tmp);
      } else {
        Events::trigger('ymsg.disconnected', array('message'=>'Socket error.'));
        throw new Exception("Error sending auth packet. ");
        
      }
    } catch(Exception $ex) {
      Events::trigger('ymsg.disconnected', array('message'=>$ex->getMessage()));
      fclose($this->socket);
    }
  }
  
  /**
   * onSentData - once we sent data (VB6.0 days!)
   * @param {string} $event - string of data we sent
   */
  public function onSentData($event) {
    // encode the original string
    $myString = str_replace(chr(0),'.',utf8_encode($event));
    // print it to the user
    printf('<p style="color:green">&gt;%s</p>',$myString);        
    
    // read the data
    $recv = fread($this->socket,4096);  
    // trigger our event
    Events::trigger('ymsg.data.recv',$recv);
    // clear data received
    unset($recv);  
  }
  
  public function onRecvData($event) {
    
    $packet= null;
    $myString = str_replace(chr(0),'.',$event);
    $service = ord(trim(substr($myString,11,1)));
    printf('<p style="color:blue">&lt;%s</p>',$myString);       
    printf("Service: <b>%d</b><br>" , $service);
    if($service == '76' || $service == 76) {
      $auth = new Auth();
      $auth->setData(array('yid'=>$this->yid));       
      $tmp = $auth;     
    }
    
    if ($service =='87' || $service == 87) {
        $challenge = $this->getBetween($myString,'94' . $this->delimeter,   $this->delimeter);
        
        $y64 = $this->Yahoo64($this->ypass,false);
        $pass = base64_decode($y64);      
        $this->GetAuth($this->yid,$pass,$challenge);           
    }
    
    if($service =='85' || $service == 85) {         
        include('views/loggedin.tpl');        
    }
    
    if(! $this->socket) Events::trigger('ymsg.disconnected', array('message'=>'Packet issues. ' . $tmp));      
         
     try {
       $pstr = str_replace(chr(0),'.',utf8_encode( $tmp));
        if(strlen($pstr)>0 && fwrite($this->socket,$tmp))
          Events::trigger('ymsg.data.sent', $tmp);
          unset($packet);         
          
      } catch(Exception $ex) {
        Events::trigger('ymsg.disconnected', array('message'=>$ex->getMessage()));
      }      
    //fclose($this->socket);       
    
  }
  
  public function onMessage($event) {
    
  }
  
  public function onDisconnect($event) {
    printf("<p style=\"color:red\">Disconnected. %s</p>", $event['message']);
  }
  
  public function onError($event) {
    printf("Error Occurred:<br>%s<br>%s<br>%s", $event['message'],$event['url'],$event['token']);
    if($this->socket){
      fclose($this->socket);
      
    }
  }
  
  /**
   * Login stufff..
   * 
   * @param {string} $yahooid
   * @param {string} $yahoopass
   * @param {string} $challenge
   * @return string 
   */
  public function GetAuth($yid, $ypwd, $ychal){
		$this->yid= $yid;
    
    $loginURL = sprintf("https://login.yahoo.com/config/pwtoken_get?src=ymsgr&ts=&login=%s&passwd=%s&chal=%s", urlencode($yid),urlencode($ypwd),urlencode($ychal));        
    $loginResp = $this->WebRequest($loginURL);        
		
    $token = $this->getBetween($loginResp, 'ymsgr=', "\n");        
    $tokenURL = sprintf("https://login.yahoo.com/config/pwtoken_login?src=ymsgr&ts=&token=%s", trim($token));
		if(!$token){
        Events::trigger('ymsg.cookie.error', array("message"=>$loginResp, "url"=>$loginURL,"token"=>$token));
        return;
    }
		
    $login = $this->WebRequest($tokenURL);
		$cookie['Y']		= $this->getBetween($login, 'Y=', "\n");
		$cookie['T'] 		= $this->getBetween($login, 'T=', "\n");	
		$cookie['crumb']	= trim($this->getBetween($login, 'crumb=', "\n"));
		$cookie['dateline']	= time();			
		$this->cookie = $cookie;    
    
    Events::trigger('ymsg.cookie.got',array('cookie'=>$cookie,'encdata'=>$this->ProcessAuth($cookie['crumb'],$ychal)));
	}	
  
  /**
   * getBetween - this accepts a char and gets the text between,
   * also could be accomplished using substring
   * 
   * @param {string} $subject - haystack to search
   * @param {string} $startString - the beginning search string
   * @param {string} $endString - end of the string to search
   * @return {string} string between start and end
   */ 
  public function getBetween($subject,$startString,$endString) {
      
    if(!$startString){
			$str = explode($endString, $subject);
			return $str[0];
		}else{
			$str = explode($startString, $subject);
			if($endString){		
				$str = explode($endString, $str[1]);
				return $str[0];
			}else
				return $str[1];
		}
  }

   /**
    * use Yahoo's messed up version of base64 to encode/decode our challenge strings
    * @param {string} $sInput - the input string to be encoded
    * @return {string} encoded/decoded string
    */
   public function Yahoo64($sInput, $encode= true) {           
    if ($encode) {
      $_tmp = base64_encode($sInput);         
      $_tmp = str_replace('+', '.', $_tmp);
      $_tmp = str_replace('/', '_', $_tmp);
      $_tmp = str_replace('=', '-', $_tmp);
    } else {
      $_tmp = $sInput;
      
      $_tmp = str_replace('.', '+', $_tmp);
      $_tmp = str_replace('_', '/', $_tmp);
      $_tmp = str_replace('-', '=', $_tmp);      
      $_tmp = base64_decode($_tmp);
    }
    return $_tmp;     
   }
      
   /**
    * ProcessAuth - this function creates the necessary "authentication" method for Yahoo! to login
    * combining the crumb and the challenge string to md5 and then using yahoo's "modified" version of base64
    * @param {string} $crumb - "glue"
    * @param {string} $challenge - challenge string
    * @return {string}
    */ 
   public function ProcessAuth($crumb, $challenge){
		$md5 = md5($crumb . $challenge);		
    for ($i = 0; $i < 32; $i += 2) {
			$encode .= chr(hexdec($md5[$i] . $md5[$i + 1]));
		}
    
    return  $this->Yahoo64($encode);
	} 
  
  
  /**
   * WebRequest - get a resource over the net using cURL
   * @param {string} $url - url you want to get
   * @param {string} $method - POST, GET, PUT, PATCH , OPTION whatever the method be
   * @param {string} $params - params to server
   * @return string
   */
  public function WebRequest($url,$method='GET', $params =array()) {
    $cookiejar = sprintf("%s/cookies/%s.txt",getcwd(),$this->yid);
    $ch = curl_init();
    curl_setopt($ch,CURLOPT_URL,$url);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch,  CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch,  CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiejar);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiejar);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION,1);  
    curl_setopt($ch,CURLOPT_USERAGENT,"Mozilla/5.0 (Windows NT 6.1; WOW64; rv:41.0) Gecko/20100101 Firefox/41.0");  
    if ($method == "POST"){
        curl_setopt($ch,CURLOPT_POST,1);
        curl_setopt($ch, CURLOPT_POST_FIELDS, http_build_query($params));
    }
    
    $ret = curl_exec($ch);
    curl_close($ch);
    return $ret;
  }
  
  /**
   * Notify - used to notify the user we've logged on.
   * @param {string} user - person we're notifying
   * @param {string} message - message to send
   */
  
  public function NotifyUser() {
    $packet = new InstantMessage('06');
    $packet->setData(array('yid'=>$this->yid, 'recipient'=>$this->notifier,'message'=>$this->msg));
     if( fwrite($this->socket,$packet)) {
       Events::trigger('ymsg.data.sent', $packet);           
     }
  }
  
  public function setNotifier($user,$msg){
    $this->notifier = $user;
    $this->msg  = $msg;
    
  }
  /**
   * Send a graceful logout packet.
   */
  public function Disconnect() {
    if($this->socket){
          fclose($this->socket);
          Events::trigger("ymsg.disconnected",array('message'=>'Session ended by user'));
    }
  }
}
ob_end_flush();
?>
