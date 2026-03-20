<?php

declare (strict_types=1);
namespace Laminas\Diactoros;

class Module
{
    public function get_config(): array
    {
        return ['service_manager' => (new Config_Provider())->get_dependencies()];
    }
}