<p>Congratulations you've logged in successfully using</p>

<?php
$recipient= 'CHANGE THIS TO THE PERSON YOU WANT TO MESSAGE';
$message = 'I\'ve successfully logged into YMSGv16!!!!';
$this->setNotifier($recipient,$message);
$this->NotifyUser();
?>
