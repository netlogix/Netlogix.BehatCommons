<?php
namespace Netlogix\BehatCommons;

/*
 * This file is part of the Netlogix.BehatCommons package.
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use TYPO3\Flow\Annotations as Flow;

/**
 *
 * @Flow\Scope("singleton")
 */
class ClassNameResolver
{
	/**
	 * @param string $package
	 * @param string $className
	 * @param string $prefix
	 * @param string $suffix
	 * @return string
	 * @throws \Exception
	 */
	public function resolveClassName($package, $className, $prefix = '', $suffix = '')
	{
		$possibleClassNames = [
			$className,
			str_replace('.', '\\', $package) . '\\' . $prefix . $className . $suffix
		];
		if ($suffix && substr($className, -1 * strlen($suffix)) === $suffix) {
			$possibleClassNames[] = str_replace('.', '\\', $package) . '\\' . $prefix . $className;
		}
		foreach ($possibleClassNames as $possibleClassName) {
			if (class_exists($possibleClassName)) {
				return $possibleClassName;
			}
		}
		throw new \Exception('Could not find class "' . $prefix . $className . $suffix . '"');
	}
}