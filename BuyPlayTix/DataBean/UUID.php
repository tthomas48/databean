<?php
namespace BuyPlayTix\DataBean;

use Rhumsaa\Uuid\Uuid as U;
class UUID
{
    public static function get()
    {
        $uuid5 = U::uuid5(U::NAMESPACE_DNS, 'secure.buyplaytix.com');
        return $uuid5->toString();
    }
}
