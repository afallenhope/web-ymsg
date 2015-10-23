<p>Congratulations you've logged in successfully using <?=$this->yid; ?></p>

<?php
$recipient= 'CHANGE THIS TO THE PERSON YOU WANT TO MESSAGE';
$message = 'I\'ve successfully logged into YMSGv16!!!!';

$packet = new InstantMessage('06');
$packet->setData(array('yid'=>$this->yid, 'recipient'=>$recipient,'message'=>$message));
  if( fwrite($this->socket,$packet)) {
    Events::trigger('ymsg.data.sent', $packet);           
 }
?>
