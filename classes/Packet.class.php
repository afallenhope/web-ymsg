<?php

if (DEBUG)
  error_reporting( E_ALL & ~E_NOTICE);


/**
 * Packet - creates a template for YMSG packets
 *
 * @param {integer} $service - decimal version of the service
 * @return {string} Packet you might want to toss a str_replace(chr(0), ".", $this) to the __toString() though.. possible a utf8_encode() too;
 */
abstract class Packet {
  
   private $protocol = "YMSG"; // always this I could turn this into a const but figured for the sake of the example
   private $delimeter; // packet delimiter
   private $version; // decimal version of the protocol we're using
   private $vendor; // default vendor
   private $length; // length of packet payload (initially not set we calculate with our checksum)
   public $status; // we get this from the server
   public $session; // we get this from the server
   private $service; // this is our place holder for ther service
   private $packet = array();         
   
   /**
    * constructor method
    * @param {string} $payload  - string payload (actual packet)
    * @param {integer} $service - decimal version of the service 
    */
   public function __construct($service='87'){
     $this->version =  chr(0x10); // protocol version 16
     $this->delimeter = chr(0xC0) . chr(0x80); // some versions of PHP don't accept the hex strings so why not attempt this 
     $this->vendor    = str_repeat(chr(0),2);
     $this->status    = str_repeat(chr(0),4); // status typicall is 0x00 0x00 (according to protocol sniffedd
     $this->session   = str_repeat(chr(0),4); // status typicall is 0x00 0x00 (according to protocol sniffedd
     $this->service   = $service; // packet service
   }
   
   /**
    * addField
    * @param {integer} $field - typical packet field
    * @param {string} $value - value field
    */   
   public function addField($field, $value) {
    $this->payload[] = $field;
    $this->payload[] = $value;
   }
   
   
   /**
    * calculate the checksum
    */
   public function createChecksum() {
     $iLen = strlen($this->payload); // initial length of our payload
     $service = $this->service; // store old service 
          
     $this->service = chr(intval($service/256)) . chr(fmod(intval($service), 256)); // update with the proper service (may be redundant)
     $this->length = chr(intval($iLen/256)) . chr(fmod($iLen, 256));  // actually calculate the 4 bytes for length (in hex)
   }   
   
   /**
    *  Turn our class object to a string
    * @return {string} - packet
    */ 
   public function __toString() {     
    $this->compilePayload(); // compile payload into a  string 
    $this->createChecksum(); // calculate the length / service of the packet
    
    // assemble the packet
    $tmp  = $this->protocol;  
    $tmp .= chr(0x00) . $this->version;
    $tmp .= $this->vendor;
    $tmp .= $this->length;
    $tmp .= $this->service;
    $tmp .= $this->status;
    $tmp .= $this->session;
    $tmp .= $this->payload;   
         
    unset($this->payload); // reset the payload
    $this->packet = $tmp; // assign the packet to the assembled packet
    return $tmp; // return the assembled packet.
   }
   
   
   /**
    *  Return only the header
    *  should be in total 20 bytes
    * @return {string} header
    */ 
   public function getHeader() {
     $this->payload = "";
      return $this;
   }
   
   /**
    * turns the array into a string assigns it to the payload
    */     
   public function compilePayload() {     
     if (is_array($this->payload) && count($this->payload) > 0 ) // make sure that we have something in our payload
     $this->payload = implode($this->delimeter,$this->payload) . $this->delimeter;  // assign the payload
   }
}
?>
