<?php
namespace BuyPlayTix\DataBean;

class Diff {
	
	private $adds = array();
	private $removes = array();

	public function __construct() {
		
	}
	
	public function diff($old_array = array(), $new_array = array()) {
		
		foreach($old_array as $key => $value) {
			if(array_key_exists($key, $new_array)) {
				$this->adds[$key] = $new_array[$key];
				$this->removes[$key] = $old_array[$key];
				continue;
			}
			
			$this->removes[$key] = $old_array[$key];
		}
		
		foreach($new_array as $key => $value) {
			if(array_key_exists($key, $old_array)) {
				continue;
			}
			$this->adds[$key] = $new_array[$key];
		}
	}
	
	public function __toString() {
		
		$output = array();
		
		$remove_keys = array_keys($this->removes);
		$add_keys = array_keys($this->adds);
		$all_keys = array_unique(array_merge($add_keys, $remove_keys));
		foreach($all_keys as $key) {
			if(array_key_exists($key, $this->adds) && array_key_exists($key, $this->removes)) {
				$old_value = $this->removes[$key];
				$new_value = $this->adds[$key];
				if($old_value != $new_value) {
				  $output[] = "Changed $key from '$old_value' to '$new_value'";
				}
				continue;
			}
			if(array_key_exists($key, $this->adds)) {
				$new_value = $this->adds[$key];
				$output[] = "Set $key to '$new_value'";
			}
			if(array_key_exists($key, $this->removes)) {
				$old_value = $this->removes[$key];
				$output[] = "Unset $key from '$old_value'";
			}			
		}
		return implode("\n", $output);
	}
}
