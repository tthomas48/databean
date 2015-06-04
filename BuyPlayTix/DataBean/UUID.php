<?php
namespace BuyPlayTix\DataBean;

use Rhumsaa\Uuid\Uuid as U;
class UUID
{
    public static function get()
    {
        $uuid5 = U::uuid5(U::NAMESPACE_DNS, 'tthomas48.github.com');
        return $uuid5->toString();
    }
}
