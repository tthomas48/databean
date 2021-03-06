<?php
namespace BuyPlayTix\DataBean;
interface IDBLogger {
  function alert($message, $vars = array());
  function critical($message, $vars = array());
  function error($message, $vars = array());
  function warning($message, $vars = array());
  function info($message, $vars = array());
  function debug($message, $vars = array());
}
