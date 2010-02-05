<?php

/**
 * Used to launch the manager process
 * @author Mike Lively <m@digitalsandwich.com>
 */
class DigitalSandwich_Silverware
{
	/**
	 * @var DigitalSandwich_Silverware_IManager
	 */
	protected $manager;

	public function __construct(DigitalSandwich_Silverware_IManager $manager)
	{
		$this->manager = $manager;
	}

	public function run(DigitalSandwich_Silverware_ThreadPool $threadPool)
	{
		$this->manager->main($threadPool);
		$threadPool->waitForThreads();
	}
}

?>
