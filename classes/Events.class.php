<?php

/**
 * Customer EventListeners
 * 
 */ 
class Events {
  
  // list of events we have;
  public static $events =array();
  
  // trigger a customer event using "call_user_func()" 
  public static function trigger($event,$args= array())  {
    if(isset(self::$events[$event])) {
      foreach(self::$events[$event] as $function) {
        call_user_func($function, $args);
      }
    }
  }
  
  // call the bind to create the event.
  public static function bind($event, Closure $function) {
      self::$events[$event][] = $function;
  }
  
  // remove an event from the list (ex: 1 time event)
  public static function remove($event){
    unset(self::$events[$event]);
  }
}
?>
