<?php namespace LaravelResource\Database\Eloquent;

trait VersionTracking
{
    public function save(array $options = [])
    {
        if ($this->exists) {
            $dirty = $this->getDirty();

            if (count($dirty) > 0) {
                $this->version++;
            }
        } else {
            $this->version = 0;
        }

        return parent::save($options);
    }
}
