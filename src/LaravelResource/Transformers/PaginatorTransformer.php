<?php namespace LaravelResource\Transformers;

use Illuminate\Contracts\Pagination\Paginator;
use LaravelResource\Transformers\Contracts\Transformer;

class PaginatorTransformer extends AbstractTransformer implements Transformer
{

    /**
     * @param Paginator $data
     * @return array
     */
    public function transform($data)
    {
        return [
            'page' => $data->currentPage(),
            'page_size' => $data->perPage(),
            'count' => $data->total(),
        ];
    }
}
