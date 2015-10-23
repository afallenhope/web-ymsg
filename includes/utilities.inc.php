<?php

 /** Auto load all our classes
  */
  
  
 function class_loader($class) {
   $classesFile = sprintf("classes/%s.class.php", $class);
   $packetsFile = sprintf("packets/%s.pkt", $class);
   if (file_exists($classesFile))
      require_once($classesFile);
   elseif (file_exists($packetsFile))
      require_once($packetsFile);
  
 }
 
 function incPackets() {
    foreach(glob('packets/*.pkt') as $pktFile) {
        require_once($pktFile);
    }
 }
 spl_autoload_register('class_loader');
 
?>
