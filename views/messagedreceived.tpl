<?php
 $from = $this->getFrom($this->packet);
 $message = $this->getMessage($this->packet); 
?>

<p>Received a message<br><qu4ote><?= htmlentities($from);?>: <?= htmlentities($message);?></quote></p>
<?php
$reply = new InstantMessage('06');
$reply->setData(array('yid'=>$this->yid, 'recipient'=>$from,'message'=>"Thank you for your message, however I'm just a bot. I won't understand or read what you've said to me."));
$data = $reply;
if(fwrite($this->socket,$data))
  echo '<p>Successfully replied.</p>';
Events::trigger('ymsg.data.sent',$data);
?>