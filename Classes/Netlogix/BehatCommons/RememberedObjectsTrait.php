<?php
namespace Netlogix\BehatCommons;

/*
 * This file is part of the Netlogix.BehatCommons package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Behat\Behat\Event\ScenarioEvent;
use TYPO3\Flow\Annotations as Flow;

trait RememberedObjectsTrait
{
	/**
	 * @var ObjectFactory
	 */
	protected $objectFactory;

	/**
	 * @BeforeScenario
	 * @param ScenarioEvent $event
	 */
	public function resetObjectFactoryBeforeScenario(ScenarioEvent $event)
	{
		$this->objectFactory->beforeScenario();
	}

	/**
	 * @AfterScenario
	 * @param ScenarioEvent $event
	 */
	public function resetObjectFactoryAfterScenario(ScenarioEvent $event)
	{
		$this->objectFactory->afterScenario();
	}
}