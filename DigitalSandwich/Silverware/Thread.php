<?php

class DigitalSandwich_Silverware_Thread
{
	/**
	 * @var int
	 */
	protected $pid;

	protected $socket;

	public function __construct(DigitalSandwich_Silverware_IWorker $worker)
	{
		socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $sockets);
		$this->pid = pcntl_fork();
		if ($this->pid == -1)
		{
			throw new RuntimeException("Could Not Fork!");
		}
		elseif ($this->pid)
		{
			socket_close($sockets[1]);
			$this->socket = $sockets[0];
			return;
		}
		else
		{
			socket_close($sockets[0]);
			$this->socket = $sockets[1];
			$value = $worker->main($this);
			$this->closeSocket();
			exit($value);
		}
	}

	public function getPid()
	{
		return $this->pid;
	}

	public function getSocket()
	{
		return $this->socket;
	}

	public function sendMessage($message)
	{
		$r = array();
		$w = array($this->getSocket());
		$e = array();
		while (socket_select($r, $w, $e, 0))
		{
			if (isset($w[0]))
			{
				socket_write($w[0], serialize($message) . "\n");
				return;
			}
		}
		if (socket_last_error() != 0)
		{
			fwrite(STDERR, "socket_select() failed: " . socket_strerror(socket_last_error()) . "\n");
		}
	}

	public function getMessages(&$socketClosed)
	{
		$data = '';
		do
		{
			$read = socket_read($this->getSocket(), 1024);
			$data .= $read;
		} while ($read !== '' && $read !== FALSE);

		$socketClosed = $read === FALSE;

		$messages = array();
		if (!strlen($data))
		{
			 return $messages;
		}
		foreach (explode("\n", trim($data)) as $row)
		{
			$messages[] = unserialize($row);
		}

		return $messages;
	}

	public function wait($options = 0)
	{
		return pcntl_waitpid($this->pid, &$status, $options);
	}

	public function closeSocket()
	{
		socket_close($this->socket);
	}
}

?>
