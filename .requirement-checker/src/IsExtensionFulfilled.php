<?php

namespace _HumbugBox5af562ac6f36a\KevinGH\RequirementChecker;

/**
@private
*/
final class IsExtensionFulfilled implements \_HumbugBox5af562ac6f36a\KevinGH\RequirementChecker\IsFulfilled
{
    private $requiredExtension;
    /**
    @param
    */
    public function __construct($requiredExtension)
    {
        $this->requiredExtension = $requiredExtension;
    }
    public function __invoke()
    {
        return \extension_loaded($this->requiredExtension);
    }
}
