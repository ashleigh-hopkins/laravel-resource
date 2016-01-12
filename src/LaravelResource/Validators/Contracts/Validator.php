<?php namespace LaravelResource\Validators\Contracts;

interface Validator
{
    /**
     * @param mixed $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    function forStore($data);

    /**
     * @param mixed $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    function forUpdate($data);
}
