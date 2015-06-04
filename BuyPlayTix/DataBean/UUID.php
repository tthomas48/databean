<?php
namespace BuyPlayTix\DataBean;

use Rhumsaa\Uuid\Uuid as U;
class UUID
{
    public static function get()
    {
        $uuid1 = U::uuid1();
        return $uuid1->toString();
    }
}
