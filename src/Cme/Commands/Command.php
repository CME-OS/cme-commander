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
    $pid       = getmypid();
    $monitDir  = 'monit/' . $this->commandName;
    $monitFile = $monitDir . '/' . $this->_getInstanceName($instId) . '.pid';
    if(!file_exists($monitDir))
    {
      mkdir($monitDir);
    }
    return (bool)file_put_contents($monitFile, $pid);
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
