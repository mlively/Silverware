<?php

require_once('DigitalSandwich/Silverware/Thread.php');

class DigitalSandwich_Silverware_ThreadPool
{
	protected $threadArray = array();
	protected $socketArray = array();
	protected $socketThreadMap = array();
	protected $maxThreads;

	protected $messageBuffer = array();

	public function __construct($maxThreads)
	{
		$this->maxThreads = $maxThreads;
	}

	public function acquireThread(DigitalSandwich_Silverware_IWorker $worker, $block = TRUE)
	{
		while (count($this->threadArray) >= $this->maxThreads)
		{
			$this->checkThreads();
			if (!$block)
			{
				return FALSE;
			}
			usleep(50);
		}

		$thread = new DigitalSandwich_Silverware_Thread($worker);
		$this->threadArray[$thread->getPid()] = $thread;
		$this->socketArray[] = $thread->getSocket();
		$this->socketThreadMap[$thread->getSocket()] = $thread;
		return TRUE;
	}

	public function checkThreads()
	{
		foreach ($this->threadArray as $thread)
		{
			$pid = $thread->wait(WNOHANG);
			if ($pid > 0)
			{
				$socketClosed = TRUE;
				$this->messageBuffer = array_merge($this->messageBuffer, $thread->getMessages($socketClosed));
				$thread->closeSocket();
				$this->removeSocket($thread->getSocket());
				unset($this->threadArray[$pid]);
			}
		}
	}

	public function getThreadCount()
	{
		$this->checkThreads();
		return count($this->threadArray);
	}

	public function waitForThreads()
	{
		foreach ($this->threadArray as $thread)
		{
			$thread->wait();
			$socketClosed = TRUE;
			$this->messageBuffer = array_merge($this->messageBuffer, $thread->getMessages($socketClosed));
			$thread->closeSocket();
			$this->removeSocket($thread->getSocket());
		}
	}

	public function getMessagesFromThreads()
	{
		if (!count($this->socketArray))
		{
			return array();
		}

		$w = array();
		$e = array();
		$sockets = $this->socketArray;
		if (FALSE === socket_select($sockets, $w, $e, 0))
		{
			fwrite(STDERR, "socket_select() failed: " . socket_strerror(socket_last_error()) . "\n");
			return array();
		}

		$messages = array();
		foreach ($sockets as $socket)
		{
			$socketClosed = FALSE;
			$messages = array_merge($messages, $this->socketThreadMap[$socket]->getMessages($socketClosed));
			if ($socketClosed)
			{
				$this->socketThreadMap[$socket]->closeSocket();
				$this->removeSocket($socket);
				unset($this->socketArray[array_search($socket, $this->socketArray)]);
			}
		}

		$buffer = $this->messageBuffer;
		$this->messageBuffer = array();
		return array_merge($buffer, $messages);
	}

	protected function removeSocket($socket)
	{
		unset($this->socketArray[array_search($socket, $this->socketArray)]);
		unset($this->socketThreadMap[$socket]);
	}
}

?>
