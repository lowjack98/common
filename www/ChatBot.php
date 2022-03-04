<?php
// vim600: fdm=marker
//////////////////////////////////////////////////////////////////////////
// ChatBot
// -------------------
// begin                : 2020-08-24
// copyright            : kietzman.org
// email                : ben@kietzman.org
//////////////////////////////////////////////////////////////////////////

//////////////////////////////////////////////////////////////////////////
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.
//////////////////////////////////////////////////////////////////////////

/*! \file ChatBot.php
* \brief ChatBot Class
*
* Provides chat bot functionality.
*/

//! ChatBot Class
/*!
* Provides chat bot functionality.
*/
class ChatBot
{
  // {{{ variables
  protected $m_bConnected;
  protected $m_bDebug;
  protected $m_bQuit;
  protected $m_bUseStream;
  protected $m_fdSocket;
  protected $m_fdStream;
  protected $m_formArguments;
  protected $m_messages;
  protected $m_port;
  protected $m_rooms;
  protected $m_strBuffer;
  protected $m_strFormAction;
  protected $m_strFormFooter;
  protected $m_strFormHeader;
  protected $m_strUser;
  protected $m_streamContext;
  protected $m_pMessage;
  protected $m_pUtility;
  // }}}
  // {{{ __construct()
  public function __construct()
  {
    $this->m_bConnected = false;
    $this->m_bDebug = false;
    $this->m_bQuit = false;
    $this->m_bUseStream = false;
    $this->m_fdSocket = -1;
    $this->m_fdStream = false;
    $this->m_formArguments = [];
    $this->m_pMessage = null;
    $this->m_messages = [];
    $this->m_port = 12199;
    $this->m_rooms = [];
    $this->m_streamContext = null;
    $this->m_strBuffer = [null, null];
    $this->m_pUtility = new Utility; // Note: The calling program needs to include 'common/www/Utility.php'.
  }
  // }}}
  // {{{ __destruct()
  public function __destruct()
  {
    if ($this->m_bConnected)
    {
      $this->disconnect();
    }
    unset($this->m_formArguments);
    unset($this->m_messages);
    unset($this->m_rooms);
    unset($this->m_pUtility);
    unset($this->m_strBuffer);
  }
  // }}}
  // {{{ analyze()
  private function analyze(&$message)
  {
    $strError = null;

    if ($this->m_pMessage != null)
    {
      call_user_func($this->m_pMessage, $message);
    }
    // {{{ Function
    if (is_array($message) && isset($message['Function']))
    {
      $bStatus = false;
      $strFunction = $message['Function'];
      $strStatus = null;
      if (isset($message['Status']))
      {
        $bStatus = true;
        $strStatus = $message['Status'];
      }
      // {{{ connect
      if ($strFunction == 'connect')
      {
        if ($strStatus == 'okay')
        {
          if (isset($message['Response']) && is_array($message['Response']) && isset($message['Response']['Rooms']) && is_array($message['Response']['Rooms']))
          {
            foreach ($message['Response']['Rooms'] as $key => $value)
            {
              $this->m_rooms[] = $key;
            }
            $this->m_rooms = array_unique($this->m_rooms, SORT_STRING);
          }
          $this->m_bConnected = true;
        }
        else if ($bStatus)
        {
          $this->disconnect();
        }
      }
      // }}}
      // {{{ disconnect
      else if ($strFunction == 'disconnect')
      {
        $this->close($strError);
      }
      // }}}
      // {{{ join
      else if ($strFunction == 'join')
      {
        if ($strStatus == 'okay')
        {
          if (isset($message['Request']) && is_array($message['Request']) && isset($message['Request']['Room']) && $message['Request']['Room'] != '')
          {
            $this->m_rooms[] = $message['Request']['Room'];
            $this->m_rooms = array_unique($this->m_rooms, SORT_STRING);
          }
        }
      }
      // }}}
      // {{{ part
      else if ($strFunction == 'part')
      {
        if ($strStatus == 'okay')
        {
          if (isset($message['Request']) && is_array($message['Request']) && isset($message['Request']['Room']) && $message['Request']['Room'] != '')
          {
            $rooms = [];
            $nSize = sizeof($this->m_rooms);
            for ($i = 0; $i < $nSize; $i++)
            {
              if ($this->m_rooms[$i] != $message['Request']['Room'])
              {
                $rooms[] = $this->m_rooms[$i];
              }
            }
            unset($this->m_rooms);
            $this->m_rooms = $rooms;
            unset($rooms);
            if ($this->m_bQuit)
            {
              if (sizeof($this->m_rooms) > 0)
              {
                $this->part($this->m_rooms[0]);
              }
              else
              {
                $this->disconnect();
              }
            }
          }
        }
      }
      // }}}
    }
    // }}}
  }
  // }}}
  // {{{ close()
  public function close(&$strError)
  {
    $bResult = false;

    $this->m_bConnected = false;
    if ($this->m_bUseStream)
    {
      if ($this->m_fdStream === false || stream_socket_shutdown($this->m_fdStream, STREAM_SHUT_RDWR) !== false)
      {
        $bResult = true;
      }
      else
      {
        $strError = 'stream_socket_shutdown() ' . var_dump(error_get_last());
      }
      $this->m_fdStream = false;
    }
    else
    {
      if ($this->m_fdSocket == -1 || socket_close($this->m_fdSocket) !== false)
      {
        $bResult = true;
      }
      else
      {
        $strError = 'close('.socket_last_error($this->m_fdSocket).') '.socket_strerror(socket_last_error($this->m_fdSocket));
      }
      $this->m_fdSocket = -1;
    }
    unset($this->m_messages);

    return $bResult;
  }
  // }}}
  // {{{ connect()
  public function connect($strServer, $strPort, $strUser, $strPassword, $strName, &$strError)
  {
    $bResult = false;
    $bSocket = false;
    $this->m_strUser = $strUser;

    if ($this->m_bUseStream)
    {
      if (!$this->m_streamContext) 
      {
        $strError = 'Stream context is not created.';
        return $bResult;
      }
      else 
      {
        if (($this->m_fdStream = $this->m_pUtility->createClientStream($strServer, $this->m_port, $this->m_streamContext, $strError)) !== false)
        {
          $bResult = true;
        }
      }
    }
    else
    {
      if (!defined('AF_INET'))
      {
        define('AF_INET', 2);
      }
      if (!defined('SOCK_STREAM'))
      {
        if (PHP_OS == 'Linux')
        {
          define('SOCK_STREAM', 1);
        }
        else if (PHP_OS == 'SunOS')
        {
          define('SOCK_STREAM', 2);
        }
      }
      if (!defined('SOL_TCP'))
      {
        define('SOL_TCP', 6);
      }

      if (($fdSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) !== false)
      {
        $bSocket = true;
        if (socket_connect($fdSocket, $strServer, $this->m_port) !== false)
        {
          $this->m_fdSocket = $fdSocket;
          $bResult = true;
        }
        else
        {
          socket_close($fdSocket);
        }
      }
    }
    if ($bResult)
    {
      $message = [];
      $message['Section'] = 'chat';
      $message['Function'] = 'connect';
      $message['User'] = $strUser;
      $message['Password'] = $strPassword;
      $message['Request'] = [];
      $message['Request']['Name'] = $strName;
      $message['wsRequestID'] = -1;
      $this->putMessage($message);
    }
    else
    {
      if (!$this->m_bUseStream)
      {
        $strError = (($bSocket)?'socket_connect':'socket_create').'('.socket_last_error().') '.socket_strerror(socket_last_error());
      }
    }

    return $bResult;
  }
  // }}}
  // {{{ disconnect()
  public function disconnect()
  {
    $message = [];

    $message['Function'] = 'disconnect';
    $message['Request'] = [];
    $this->putMessage($message);
    unset($message);
  }
  // }}}
  // {{{ form()
  public function form($strIdent, $strForm, $arguments = [], $strHeader = '', $strFooter = '')
  {
    $ssForm = '<form onsubmit="fetch(\''.$this->m_strFormAction.'\', {method: \'POST\', cache: \'no-cache\', body: new URLSearchParams(new FormData(this))}).then(response =&gt; {}); return false;">';
    $ssForm .= '<input type="hidden" name="botIdent" value="'.$strIdent.'">';
    foreach ($this->m_formArguments as $key => $value)
    {
      $ssForm .= '<input type="hidden" name="'.$key.'" value="'.$value.'">';
    }
    foreach ($arguments as $key => $value)
    {
      $ssForm .= '<input type="hidden" name="'.$key.'" value="'.$value.'">';
    }
    $ssForm .= '<div style="border-style: solid; border-width: 1px; border-color: #4e5964; border-radius: 10px; background: #2c3742; padding: 10px; color: white;">';
    if ($this->m_strFormHeader != '')
    {
      $ssForm .= $this->m_strFormHeader;
    }
    if ($strHeader != '')
    {
      $ssForm .= $strHeader;
    }
    $ssForm .= $strForm;
    if ($strFooter != '')
    {
      $ssForm .= $strFooter;
    }
    if ($this->m_strFormFooter != '')
    {
      $ssForm .= $this->m_strFormFooter;
    }
    $ssForm .= '</div>';
    $ssForm .= '</form>';

    return $ssForm;
  }
  // }}}
  // {{{ ident()
  public function ident($strUser)
  {
    return md5($strUser.','.microtime(true));
  }
  // }}}
  // {{{ join()
  public function join($strRoom)
  {
    $message = [];

    $message['Function'] = 'join';
    $message['Request'] = [];
    $message['Request']['Room'] = $strRoom;
    $this->putMessage($message);
    unset($message);
  }
  // }}}
  // {{{ message()
  public function message($strTarget, $strMessage, $strText = '')
  {
    $message = [];

    $message['Function'] = 'message';
    $message['Request'] = [];
    $message['Request']['Target'] = $strTarget;
    $message['Request']['Message'] = $strMessage;
    if ($strText != '')
    {
      $message['Request']['Text'] = $strText;
    }
    $this->putMessage($message);
  }
  // }}}
  // {{{ part()
  public function part($strRoom)
  {
    $message = [];

    $message['Function'] = 'part';
    $message['Request'] = [];
    $message['Request']['Room'] = $strRoom;
    $this->putMessage($message);
    unset($message);
  }
  // }}}
  // {{{ ping()
  public function ping()
  {
    $message = [];

    $message['Function'] = 'ping';
    $message['Request'] = [];
    $this->putMessage($message);
    unset($message);
  }
  // }}}
  // {{{ process()
  public function process($nTimeout, &$strError)
  {
    $bResult = false;

    if ((!$this->m_bUseStream && $this->m_fdSocket != -1) || ($this->m_bUseStream && $this->m_fdStream))
    {
      $readfds = ($this->m_bUseStream)?[$this->m_fdStream]:[$this->m_fdSocket];
      $writefds = [];
      if ($this->m_strBuffer[1] != '')
      {
        $writefds = ($this->m_bUseStream)?[$this->m_fdStream]:[$this->m_fdSocket];
      }
      else if (sizeof($this->m_messages) > 0)
      {
        $debug = $this->m_messages[0];
        if ($this->m_bConnected || (is_array($debug) && isset($debug['Function']) && $debug['Function'] == 'connect'))
        {
          $writefds = ($this->m_bUseStream)?[$this->m_fdStream]:[$this->m_fdSocket];
          if ($this->m_bDebug)
          {
            if (is_array($debug) && isset($debug['Password']))
            {
              $debug['Password'] = '******';
            }
            echo 'WRITE:  '.json_encode($debug)."\n";
          }
          $this->m_strBuffer[1] .= json_encode($this->m_messages[0])."\n";
          $messages = [];
          $nSize = sizeof($this->m_messages);
          for ($i = 1; $i < $nSize; $i++)
          {
            $messages[] = $this->m_messages[$i];
          }
          unset($this->m_messages);
          $this->m_messages = $messages;
          unset($messages);
        }
        unset($debug);
      }

      $errorfds = null;
      if ((!$this->m_bUseStream && (($nReturn = socket_select($readfds, $writefds, $errorfds, 0, ($nTimeout * 1000))) > 0)) || ($this->m_bUseStream && (($nReturn = stream_select($readfds, $writefds, $errorfds, 0, ($nTimeout * 1000))) > 0)))
      {
        if ((!$this->m_bUseStream && in_array($this->m_fdSocket, $readfds)) || ($this->m_bUseStream && in_array($this->m_fdStream, $readfds)))
        {
          if ((!$this->m_bUseStream && ($strBuffer = socket_read($this->m_fdSocket, 65536)) !== false) || ($this->m_bUseStream && ($strBuffer = fread($this->m_fdStream, 65536)) !== false))
          {
            if ($strBuffer != '')
            {
              $bResult = true;
              $this->m_strBuffer[0] .= $strBuffer;
              while (($unPosition = strpos($this->m_strBuffer[0], "\n")) !== false)
              {
                $message = json_decode(substr($this->m_strBuffer[0], 0, $unPosition), true);
                $this->m_strBuffer[0] = substr($this->m_strBuffer[0], ($unPosition + 1), (strlen($this->m_strBuffer[0]) - ($unPosition + 1)));
                if ($this->m_bDebug)
                {
                  $debug = $message;
                  if (is_array($debug) && isset($debug['Password']))
                  {
                    $debug['Password'] = '******';
                  }
                  echo 'READ:  '.json_encode($debug)."\n";
                  unset($debug);
                }
                $this->analyze($message);
                unset($message);
              }
            }
          }
          else
          {
            if ($this->m_bUseStream)
            {
              $strError = 'fread() ' . var_dump(error_get_last());
            }
            else
            {
              $strError = 'socket_read('.socket_last_error($this->m_fdSocket).') '.socket_strerror(socket_last_error($this->m_fdSocket));
            }
          }
        }
        if ((!$this->m_bUseStream && in_array($this->m_fdSocket, $writefds)) || ($this->m_bUseStream && in_array($this->m_fdStream, $writefds)))
        {
          if ((!$this->m_bUseStream && ($nReturn = socket_write($this->m_fdSocket, $this->m_strBuffer[1])) !== false) || ($this->m_bUseStream && ($nReturn = fwrite($this->m_fdStream, $this->m_strBuffer[1])) !== false))
          {
            $bResult = true;
            $this->m_strBuffer[1] = substr($this->m_strBuffer[1], $nReturn, (strlen($this->m_strBuffer[1]) - $nReturn));
          }
          else
          {
            if ($this->m_bUseStream)
            {
              $strError = 'fwrite() ' . var_dump(error_get_last());
            }
            else
            {
              $strError = 'socket_write('.socket_last_error($this->m_fdSocket).') '.socket_strerror(socket_last_error($this->m_fdSocket));
            }
          }
        }
      }
      else if ($nReturn === false)
      {
        if ($this->m_bUseStream)
        {
          $strError = 'stream_select() ' . var_dump(error_get_last());
        }
        else
        {
          $strError = 'socket_select('.socket_last_error().') '.socket_strerror(socket_last_error());
        }
      }
      else
      {
        $bResult = true;
      }
      if (!$bResult)
      {
        $this->close($strError);
      }
    }
    else
    {
      if ($this->m_bUseStream)
      {
        $strError = 'Please provide the stream resource.';
      }
      else
      {
        $strError = 'Please provide the socket file descriptor.';
      }
      $this->close($strError);
    }
    return $bResult;
  }
  // }}}
  // {{{ putMessage()
  public function putMessage(&$message)
  {
    $this->m_messages[] = $message;
  }
  // }}}
  // {{{ quit()
  public function quit()
  {
    $this->m_bQuit = true;
    if (sizeof($this->m_rooms) > 0)
    {
      $this->part($this->m_rooms[0]);
    }
    else
    {
      $this->disconnect();
    }
  }
  // }}}
  // {{{ rooms()
  public function rooms()
  {
    return $this->m_rooms;
  }
  // }}}
  // {{{ setDebug()
  public function setDebug($bDebug)
  {
    $this->m_bDebug = $bDebug;
  }
  // }}}
  // {{{ setForm()
  public function setForm($strAction, $arguments = [], $strHeader = '', $strFooter = '')
  {
    $this->m_strFormAction = $strAction;
    if (sizeof($arguments) > 0)
    {
      $this->m_formArguments = $arguments;
    }
    if ($strHeader != '')
    {
      $this->m_strFormHeader = $strHeader;
    }
    if ($strFooter != '')
    {
      $this->m_strFormFooter = $strFooter;
    }
  }
  // }}}
  // {{{ setFormArguments()
  public function setFormArguments($arguments)
  {
    $this->m_formArguments = $arguments;
  } 
  // }}}
  // {{{ setFormFooter()
  public function setFormFooter($strFooter)
  {
    $this->m_strFormFooter = $strFooter;
  } 
  // }}}
  // {{{ setFormHeader()
  public function setFormHeader($strHeader)
  {
    $this->m_strFormHeader = $strHeader;
  }
  // }}}
  // {{{ setMessage()
  public function setMessage($pMessage)
  {
    $this->m_pMessage = $pMessage;
  }
  // }}}
  // {{{ useStream()
  public function useStream($bUseStream, $streamContext)
  {
    $this->m_bUseStream = $bUseStream;
    $this->m_streamContext = $streamContext;
  }
  // }}}
}
?>
