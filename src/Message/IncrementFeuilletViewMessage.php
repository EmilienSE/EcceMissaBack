<?php

namespace App\Message;

class IncrementFeuilletViewMessage
{
    private int $feuilletId;

    public function __construct(int $feuilletId)
    {
        $this->feuilletId = $feuilletId;
    }

    public function getFeuilletId(): int
    {
        return $this->feuilletId;
    }
}
