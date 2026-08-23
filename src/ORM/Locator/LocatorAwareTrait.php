<?php
declare(strict_types=1);

/**
 * Copyright 2015 - 2020, Cake Development Corporation (http://cakedc.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2015 - 2020, Cake Development Corporation (http://cakedc.com)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
namespace CakeDC\OracleDriver\ORM\Locator;

use CakeDC\OracleDriver\ORM\MethodRegistry;

/**
 * Contains method for setting and accessing LocatorInterface instance
 */
trait LocatorAwareTrait
{
    /**
     * Method locator instance
     *
     * @var \CakeDC\OracleDriver\ORM\Locator\LocatorInterface
     */
    protected $_methodLocator;

    /**
     * Sets the method locator.
     * If no parameters are passed, it will return the currently used locator.
     *
     * @param \CakeDC\OracleDriver\ORM\Locator\LocatorInterface|null $methodLocator LocatorInterface instance.
     * @return \CakeDC\OracleDriver\ORM\Locator\LocatorInterface
     */
    public function methodLocator(?LocatorInterface $methodLocator = null)
    {
        if ($methodLocator instanceof \CakeDC\OracleDriver\ORM\Locator\LocatorInterface) {
            $this->_methodLocator = $methodLocator;
        }
        
        if (!$this->_methodLocator) {
            $this->_methodLocator = MethodRegistry::locator();
        }

        return $this->_methodLocator;
    }
}
