<?php
/**
 * @author  oke.ugwu
 */

namespace Cme\Commands;

abstract class Command
{
  public $commandName;

  abstract public function run();

  protected function _createPIDFile($instId)
  {
    if($instId)
    {
      $pid       = getmypid();
      $monitDir  = 'monit/' . $this->commandName;
      $monitFile = $monitDir . '/' . $instId . '.pid';
      if(!file_exists($monitDir))
      {
        mkdir($monitDir);
      }
      return (bool)file_put_contents($monitFile, $pid);
    }
    else
    {
      throw new \Exception("You must specify an instance ID");
    }
  }

  protected function _getInstanceName($instId)
  {
    return gethostname() . '-' . $instId;
  }

  public function getArguments()
  {
    return [];
  }
}
