<?php

class UniqueValidator extends Validator
{
	public function validate()
	{
		$allok = true;
		foreach($this->keys as $key) {
			$value = $this->model->$key;
			$count = $this->model->countByAttributes(array($key => $value));
			if($count > 0) {
				$allok = false;
				$this->error .= nf_t('$key must be unique.', array('$key' => $key)) . "\n";
			}
		}
		return $allok;
	}
}
