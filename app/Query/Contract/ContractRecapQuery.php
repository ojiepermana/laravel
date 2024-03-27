<?php

namespace App\Query\Contract;

use Illuminate\Support\Facades\Pipeline;
use Illuminate\Support\Facades\Request;

class ContractRecapQuery
{
    protected $request;
    public function __invoke(Request $request)
    {
        $this->request = $request;
        $pipe = [
            self::select()
        ];
        return Pipeline::send('select')
            ->through($pipe)
            ->thenReturn();
    }
    function select()
    {
        $this->request;
    }
}
