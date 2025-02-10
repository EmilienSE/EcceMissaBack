<?php

namespace App\Security;

use Symfony\Component\Security\Core\Util\SecureRandom;

class TokenGenerator
{
    public function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}