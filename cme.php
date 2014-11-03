#!/usr/bin/env php
<?php
/**
 * @author  oke.ugwu
 */

include 'vendor/autoload.php';

$commandStr = $argv[1];
$className = '\\Cme\\Commands\\' . $commandStr;
if(class_exists($className))
{
  $command = new $className;
  if($command instanceof \Cme\Commands\Command)
  {
    $args                 = $command->getArguments();
    $command->commandName = $commandStr;

    foreach($argv as $i => $v)
    {
      if($i > 1)
      {
        if(substr($v, 0, 1) == '-')
        {
          $short = substr($v, 1, 1);
          $value = substr($v, 2);

          if(isset($args[$short]))
          {
            $prop           = $args[$short];
            $command->$prop = $value;
          }
        }
      }
    }

    $command->run();
  }
  else
  {
    die($className . " is not a valid command");
  }
}
else
{
  die($className . " does not exist");
}




