<?php
/**
 * @author  oke.ugwu
 */

namespace Cme\Commands;

use Nette\Mail\Message;
use Nette\Mail\SmtpMailer;

class SendEmails extends Command
{
  /**
   * @var \mysqli;
   */
  private $_dbConn;
  private $_config;
  private $_queueTable = 'message_queue';
  public $batchSize = 100;
  public $instId;

  public function run()
  {
    //create PID file
    if($this->_createPIDFile($this->instId))
    {
      $this->_loadConfigs();
      //connect to db
      $this->_connectToDB();
      $instanceName = $this->_getInstanceName($this->instId);

      while(true)
      {
        //read jobs from queue
        $query = $this->_dbConn->query(
          sprintf(
            'SELECT * FROM %s WHERE locked_by=%s AND status=%s ORDER BY priority DESC LIMIT %d',
            $this->_queueTable,
            $instanceName,
            'pending',
            (int)$this->batchSize
          )
        );

        $messages = $query->fetch_object();

        if($messages)
        {
          //process it
          foreach($messages as $message)
          {
            //send email
            $emailSent = $this->sendEmail(
              $message->to,
              $message->from,
              $message->subject,
              $message->html_content
            );

            //unlock message and set the appropriate status
            $status = ($emailSent) ? 'sent' : 'failed';
            $sql    = sprintf(
              "UPDATE %s SET locked_by=NULL, status=%s
              WHERE id=%d",
              $this->_queueTable,
              $status,
              $message->id
            );
            $this->_dbConn->query($sql);
            //update analytics
          }
        }
        else
        {
          //lock some messages
          $sql = sprintf(
            "UPDATE %s SET locked_by=%s
            WHERE locked_by=NULL
            AND send_time < %d
            AND status=%s
            ORDER BY priority DESC",
            $this->_queueTable,
            $instanceName,
            time(),
            'pending'
          );
          $this->_dbConn->query($sql);
        }
      };
    }
    else
    {
      echo "Failed to create PID for monit" . PHP_EOL;
    }
  }

  private function _connectToDB()
  {
    if($this->_dbConn === null)
    {
      if(isset($this->_config['database']))
      {
        $this->_dbConn = new \mysqli(
          $this->_config['database']['host'],
          $this->_config['database']['username'],
          $this->_config['database']['password'],
          $this->_config['database']['database']
        );
      }
      else
      {
        die("config.php file does not contain database config");
      }
    }
  }

  private function sendEmail($to, $from, $subject, $body)
  {
    if(isset($this->_config['smtp']))
    {
      $mail = new Message();
      $mail->setFrom($from)
        ->addTo($to)
        ->setSubject($subject)
        ->setBody($body);
      try
      {
        $mailer = new SmtpMailer(
          [
          'host'     => $this->_config['smtp']['host'],
          'username' => $this->_config['smtp']['username'],
          'password' => $this->_config['smtp']['password'],
          'port'     => $this->_config['smtp']['port']
          ]
        );
        $mailer->send($mail);
        $return = true;
      }
      catch(\Exception $e)
      {
        $return = false;
      }

      return $return;
    }
    else
    {
      die("config.php file does not contain smtp config");
    }
  }

  private function _loadConfigs()
  {
    if(file_exists('config.php'))
    {
      $this->_config = include 'config.php';
    }
    else
    {
      die("config.php file does not exist."
        . " This file is needed to read database and smtp configs");
    }
  }

  public function getArguments()
  {
    return ['i' => 'instId', 'b' => 'batchSize'];
  }
}
