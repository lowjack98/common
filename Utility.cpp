// vim: syntax=cpp
// vim600: fdm=marker
/* -*- c++ -*- */
///////////////////////////////////////////
// Utility
// -------------------------------------
// file       : Utility.cpp
// author     : Ben Kietzman
// begin      : 2013-11-30
// copyright  : kietzman.org
// email      : ben@kietzman.org
///////////////////////////////////////////

/**************************************************************************
*                                                                         *
*   This program is free software; you can redistribute it and/or modify  *
*   it under the terms of the GNU General Public License as published by  *
*   the Free Software Foundation; either version 2 of the License, or     *
*   (at your option) any later version.                                   *
*                                                                         *
**************************************************************************/

/*! \file Utility.cpp
* \brief Utility Class
*/
// {{{ includes
#include "Utility"
// }}}
extern "C++"
{
  namespace common
  {
    // {{{ Utility()
    Utility::Utility(string &strError)
    {
      m_ulModifyTime = 0;
      m_strConf = "/etc/central.conf";
      m_conf = new Json;
      readConf(strError);
    }
    // }}}
    // {{{ ~Utility()
    Utility::~Utility()
    {
      if (m_conf != NULL)
      {
        delete m_conf;
      }
    }
    // }}}
    // {{{ conf()
    Json *Utility::conf()
    {
      return m_conf;
    }
    // }}}
    // {{{ daemonize()
    void Utility::daemonize()
    {
      int nPid = 0;

      if (getpid() == 1)
      {
        return;
      }
      nPid = fork();
      if (nPid < 0)
      {
        exit(1);
      }
      if (nPid > 0)
      {
        exit(0);
      }
      setsid();
      close(1);
      close(2);
      (void)(dup(open("/dev/null", O_RDWR))+1);
      signal(SIGCHLD, SIG_IGN);
      signal(SIGTSTP, SIG_IGN);
      signal(SIGTTOU, SIG_IGN);
      signal(SIGTTIN, SIG_IGN);
    }
    // }}}
    // {{{ getConfPath()
    string Utility::getConfPath()
    {
      return m_strConf;
    }
    // }}}
    // {{{ getLine()
    bool Utility::getLine(FILE *pFile, string &strLine)
    {
      int nChar;

      strLine.clear();
      while ((nChar = fgetc(pFile)) != EOF && (char)nChar != '\n')
      {
        strLine += (char)nChar;
      }
      
      return (!strLine.empty() || nChar != EOF);
    }
    bool Utility::getLine(gzFile pgzFile, string &strLine)
    {
      char cChar;
      int nSize = 0;

      strLine.clear();
      while ((nSize = gzread(pgzFile, &cChar, 1)) == 1 && cChar != '\n')
      {
        strLine += cChar;
      }

      return (!strLine.empty() || nSize == 1);
    }
    bool Utility::getLine(int fdFile, string &strLine, const time_t CTimeout, int &nReturn)
    {
      bool bExit = false;
      char cChar;
      time_t CEnd, CStart;

      strLine.clear();
      time(&CStart);
      while (!bExit)
      {
        pollfd fds[1];
        fds[0].fd = fdFile;
        fds[0].events = POLLIN;
        if ((nReturn = poll(fds, 1, 250)) > 0)
        {
          bool bRead = false;
          if (fds[0].fd == fdFile && (fds[0].revents & POLLIN))
          {
            if ((nReturn = read(fdFile, &cChar, 1)) == 1)
            {
              bRead = true;
              if (cChar == '\n')
              {
                bExit = true;
              }
              else
              {
                strLine += cChar;
              }
            }
            else
            {
              bExit = true;
            }
          }
          if (!bRead)
          {
            msleep(250);
          }
        }
        else if (nReturn < 0)
        {
          bExit = true;
        }
        time(&CEnd);
        if (CTimeout > 0 && (CEnd - CStart) > CTimeout)
        {
          bExit = true;
        }
      }

      return (!strLine.empty() || nReturn == 1);
    }
    bool Utility::getLine(int fdFile, string &strLine, int &nReturn)
    {
      return getLine(fdFile, strLine, 0, nReturn);
    }
    bool Utility::getLine(int fdFile, string &strLine)
    {
      int nReturn;

      return getLine(fdFile, strLine, nReturn);
    }
    bool Utility::getLine(ifstream &inFile, string &strLine)
    {
      char cChar;

      strLine.clear();
      while (inFile.get(cChar) && cChar != '\n')
      {
        strLine += cChar;
      }

      return (!strLine.empty() || inFile.good());
    }
    bool Utility::getLine(istream &inStream, string &strLine)
    {
      char cChar;

      strLine.clear();
      while (inStream.get(cChar) && cChar != '\n')
      {
        strLine += cChar;
      }

      return (!strLine.empty() || inStream.good());
    }
    bool Utility::getLine(SSL *ssl, string &strLine, const time_t CTimeout, int &nReturn)
    {
      bool bExit = false;
      char cChar;
      time_t CEnd, CStart;

      strLine.clear();
      time(&CStart);
      while (!bExit)
      {
        bool bRead = false;
        if ((nReturn = SSL_read(ssl, &cChar, 1)) == 1)
        {
          bRead = true;
          if (cChar == '\n')
          {
            bExit = true;
          }
          else
          {
            strLine += cChar;
          }
        }
        else if (nReturn < 0)
        {
          bExit = true;
          switch (SSL_get_error(ssl, nReturn))
          {
            case SSL_ERROR_NONE : break;
            case SSL_ERROR_ZERO_RETURN : break;
            case SSL_ERROR_WANT_READ : bExit = false; break;
            case SSL_ERROR_WANT_WRITE : break;
            case SSL_ERROR_WANT_CONNECT : break;
            case SSL_ERROR_WANT_ACCEPT : break;
            case SSL_ERROR_WANT_X509_LOOKUP : break;
            case SSL_ERROR_SYSCALL : break;
            case SSL_ERROR_SSL : break;
          }
        }
        if (!bRead)
        {
          msleep(250);
        }
        time(&CEnd);
        if (CTimeout > 0 && (CEnd - CStart) > CTimeout)
        {
          bExit = true;
        }
      }

      return (!strLine.empty() || nReturn == 1);
    }
    bool Utility::getLine(SSL *ssl, string &strLine, int &nReturn)
    {
      return getLine(ssl, strLine, 0, nReturn);
    }
    bool Utility::getLine(SSL *ssl, string &strLine)
    {
      int nReturn;

      return getLine(ssl, strLine, nReturn);
    }
    bool Utility::getLine(stringstream &ssData, string &strLine)
    {
      char cChar;

      strLine.clear();
      while (ssData.get(cChar) && cChar != '\n')
      {
        strLine += cChar;
      }

      return (!strLine.empty() || ssData);
    }
    // }}}
    // {{{ isProcessAlreadyRunning()
    bool Utility::isProcessAlreadyRunning(const string strProcess)
    {
      bool bResult = false;
      list<string> procList;

      m_file.directoryList("/proc", procList);
      for (list<string>::iterator i = procList.begin(); !bResult && i != procList.end(); i++)
      {
        if ((*i)[0] != '.' && m_file.directoryExist((string)"/proc/" + *i))
        {
          #ifdef COMMON_LINUX
          if (m_file.fileExist((string)"/proc/" + *i + (string)"/stat"))
          {
            ifstream inStat(((string)"/proc/" + (*i) + (string)"/stat").c_str());
            if (inStat.good())
            {
              pid_t nPid, nPPid;
              string strDaemon, strState;
              inStat >> nPid >> strDaemon >> strState >> nPPid;
              strDaemon.erase(0, 1);
              strDaemon.erase(strDaemon.size() - 1, 1);
              if (strDaemon == strProcess && nPid != getpid())
              {
                bResult = true;
              }
            }
            inStat.close();
          }
          #endif
          #ifdef COMMON_SOLARIS
          if (m_file.fileExist((string)"/proc/" + *i + (string)"/psinfo"))
          {
            ifstream inProc(((string)"/proc/" + *i + (string)"/psinfo").c_str(), ios::in|ios::binary);
            psinfo tInfo;
            if (inProc.good() && inProc.read((char *)&tInfo, sizeof(psinfo)).good() && (string)tInfo.pr_fname == strProcess && atoi(i->c_str()) != getpid())
            {
              bResult = true;
            }
            inProc.close();
          }
          #endif
        }
      }
      procList.clear();

      return bResult;
    }
    // }}}
    // {{{ msleep()
    void Utility::msleep(const unsigned long ulMilliSec)
    {
      struct timespec rqtp, *rmtp = NULL;

      rqtp.tv_sec = (time_t)(ulMilliSec / 1000);
      rqtp.tv_nsec = (ulMilliSec - ((ulMilliSec / 1000) * 1000)) * 1000000L;
      nanosleep(&rqtp, rmtp);
      if (rmtp != NULL)
      {
        delete rmtp;
      }
    }
    // }}}
    // {{{ readConf()
    bool Utility::readConf(string &strError)
    {
      bool bResult = false;
      struct stat tStat;

      m_mutexConf.lock();
      if (stat(m_strConf.c_str(), &tStat) == 0)
      {
        if (m_ulModifyTime != tStat.st_mtime)
        {
          ifstream inFile(m_strConf.c_str());
          if (inFile.good())
          {
            string strLine;
            m_ulModifyTime = tStat.st_mtime;
            if (getLine(inFile, strLine))
            {
              bResult = true;
              if (m_conf != NULL)
              {
                delete m_conf;
              }
              m_conf = new Json(strLine);
            }
            else
            {
              strError = "Failed to read central configuration line.";
            }
          }
          else
          {
            strError = "Failed to open central configuration file for reading.";
          }
          inFile.close();
        }
      }
      else
      {
        strError = "Unable to locate central configuration file.";
      }
      m_mutexConf.unlock();

      return bResult;
    }
    // }}}
    // {{{ setConfPath()
    void Utility::setConfPath(const string strPath, string &strError)
    {
      m_strConf = strPath;
      m_ulModifyTime = 0;
      readConf(strError);
    }
    // }}}
  }
}