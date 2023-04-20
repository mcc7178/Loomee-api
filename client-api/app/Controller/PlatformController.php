<?php

namespace App\Controller;

use App\Model\Platform;

class PlatformController extends AbstractController
{
    public function info()
    {
        return $this->success(Platform::info());
    }
}