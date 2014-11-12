<?php
/**
 * @author  oke.ugwu
 */

namespace Cme\Commands;

use PHPMailer;

class SendEmails extends Command
{
  /**
   * @var \mysqli;
   */
  private $_dbConn;
  private $_config;
  private $_queueTable = 'message_queue';
  private $_mailer;
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
            'SELECT * FROM %s WHERE locked_by="%s"
            AND status ="%s" ORDER BY send_priority DESC LIMIT %d',
            $this->_queueTable,
            $instanceName,
            'pending',
            (int)$this->batchSize
          )
        );

        $messages = $query->fetch_all(MYSQL_ASSOC);
        if($messages)
        {
          //process it
          foreach($messages as $message)
          {
            //trick to convert message from an array to an object
            $message = json_decode(json_encode($message));
            //send email
            $emailSent = $this->sendEmail(
              $message->to,
              $message->from_name,
              $message->from_email,
              $message->subject,
              $message->html_content
            );

            //unlock message and set the appropriate status
            $status = ($emailSent) ? 'Sent' : 'Failed';
            $sql    = sprintf(
              "UPDATE %s SET locked_by=NULL, status='%s'
              WHERE id=%d",
              $this->_queueTable,
              $status,
              $message->id
            );
            $this->_dbConn->query($sql);
            //update analytics
            //most provider expect you to send an email every second.
            //So lets sleep for 1 sec
            sleep(1);

            $sql = $this->_dbConn->query(
              sprintf(
                "INSERT INTO campaign_events (campaign_id, list_id, subscriber_id, event_type, time)
                VALUES (%d, %d, %d, '%s', %d)",
                $message->campaign_id,
                $message->list_id,
                $message->subscriber_id,
                strtolower($status),
                time()
              )
            );
            $this->_dbConn->query($sql);
          }
        }
        else
        {
          echo "Locking some rows" . PHP_EOL;
          //lock some messages
          $sql = sprintf(
            "UPDATE %s SET locked_by='%s'
            WHERE locked_by IS NULL
            AND send_time < %d
            AND status='%s'
            ORDER BY send_priority DESC
            LIMIT %d",
            $this->_queueTable,
            $instanceName,
            time(),
            'Pending',
            $this->batchSize
          );
          $this->_dbConn->query($sql);
          //if process could not lock any rows. Lets take a break
          //to avoid overloading the server
          if($this->_dbConn->affected_rows == 0)
          {
            sleep(5);
            echo @date('Y-m-d H:i:s') . ": Sleeping for a bit" . PHP_EOL;
          }
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
        if(!$this->_dbConn->ping())
        {
          die("Could not connect to datbase. Check your config please");
        }
      }
      else
      {
        die("config.php file does not contain database config");
      }
    }
  }

  private function sendEmail($to, $fromName, $fromEmail, $subject, $body)
  {
    if(isset($this->_config['smtp']))
    {
      if($this->_mailer == null)
      {
        $this->_mailer = new PHPMailer();
        $this->_mailer->isSMTP();
        $this->_mailer->SMTPAuth   = true;
        $this->_mailer->SMTPSecure = 'tls';
        $this->_mailer->Host       = $this->_config['smtp']['host'];
        $this->_mailer->Username   = $this->_config['smtp']['username'];
        $this->_mailer->Password   = $this->_config['smtp']['password'];
        $this->_mailer->Port       = $this->_config['smtp']['port'];
      }

      $this->_mailer->isHTML(true);
      $this->_mailer->addAddress($to);
      $this->_mailer->From     = $fromEmail;
      $this->_mailer->FromName = $fromName;
      $this->_mailer->Subject  = $subject;
      $this->_mailer->Body     = $body;
      if($this->_mailer->send())
      {
        echo "Sending to $to" . PHP_EOL;
        $return = true;
      }
      else
      {
        echo $this->_mailer->ErrorInfo . PHP_EOL;
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
