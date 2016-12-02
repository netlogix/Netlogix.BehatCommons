<?php
namespace Netlogix\BehatCommons\Tests\Behavior;

/*
 * This file is part of the Netlogix.BehatCommons package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Behat\Behat\Context\BehatContext;
use Behat\Gherkin\Node\TableNode;
use Netlogix\BehatCommons\ObjectFactory;
use Netlogix\BehatCommons\RememberedObjectsTrait;
use TYPO3\Flow\Object\ObjectManagerInterface;

require_once(__DIR__ . '/../../../../Classes/Netlogix/BehatCommons/RememberedObjectsTrait.php');

class RememberedObjectsContext extends BehatContext
{
	use RememberedObjectsTrait;

	/**
	 * @var ObjectManagerInterface
	 */
	protected $objectManager;

	/**
	 * @var ObjectFactory
	 */
	protected $objectFactory;

	/**
	 * @param ObjectManagerInterface $objectManager
	 */
	public function __construct(ObjectManagerInterface $objectManager)
	{
		$this->objectManager = $objectManager;
		$this->objectFactory = $this->objectManager->get(ObjectFactory::class);
	}

	/**
	 * @Given /^I remember this object as "([^"]*)"$/
	 * @param string $identifier
	 */
	public function iRemeberThisObjectAs($identifier)
	{
		$this->objectFactory->rememberLastObjectAs($identifier);
	}

	/**
	 * @Given /^I set the following properties to remembered object "([^"]*)"$/
	 * @param string $identifier
	 * @param TableNode $values
	 */
	public function iSetTheFollowingPropertiesToRememberedObject($identifier, TableNode $values)
	{
		$subject = $this->objectFactory->getRememberedObject($identifier);
		foreach ($this->objectFactory->getFlatMap($values->getRowsHash()) as $propertyPathString => $value) {
			$this->objectFactory->setPropertyPath($subject, $propertyPathString, $value);
		}
	}
}