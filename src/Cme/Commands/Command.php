<?php
/**
 * @author  oke.ugwu
 */

namespace Cme\Commands;

use \Nette\Mail\Message;
use Simplon\Mysql\Mysql;

abstract class Command
{
  public $commandName;

  abstract public function run();

  protected function _createPIDFile($instId)
  {
    $pid       = getmypid();
    $monitFile = 'monit/' . $this->commandName
      . '/' . $this->_getInstanceName($instId) . '.pid';
    if(!file_exists($monitFile))
    {
      mkdir('monit/' . $this->commandName);
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
