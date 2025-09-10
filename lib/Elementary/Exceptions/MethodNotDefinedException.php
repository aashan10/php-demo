<?php

namespace Elementary\Exceptions;

use Exception;

class MethodNotDefinedException extends Exception
{
    public function __construct(private string $methodName, private object $object)
    {
        $className = get_class($object);
        $this->message = "Call to undefined method `$methodName` in an instance of class `$className`";
    }
}
