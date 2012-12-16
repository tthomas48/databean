<?php
namespace BuyPlayTix\DataBean;
class UUID
{
    public static function get()
    {
        return uuid_create();
    }
}
?>
