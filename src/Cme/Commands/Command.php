<?php
/**
 * @author  oke.ugwu
 */

namespace Cme\Commands;

abstract class Command
{
  public $commandName;
  public $baseDir;

  abstract public function run();

  protected function _createPIDFile($instId)
  {
    if($instId)
    {
      $pid       = getmypid();
      $monitDir  = $this->baseDir . '/monit/' . $this->commandName;
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
