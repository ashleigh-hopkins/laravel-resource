<?php

if(function_exists('filter_null') == false)
{
    function filter_null($data)
    {
        return array_filter($data, function($i)
        {
            return $i !== null;
        });
    }
}