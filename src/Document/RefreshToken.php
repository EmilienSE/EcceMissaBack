<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use Gesdinet\JWTRefreshTokenBundle\Document\RefreshToken as BaseRefreshToken;

/**
 * @ODM\Document(collection="refresh_tokens")
 */
class RefreshToken extends BaseRefreshToken
{
}