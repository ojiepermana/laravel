<?php

namespace App\Filters\Contract;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Pipeline;

class ContractFilter
{
    public function __invoke(Request $request)
    {
        $pipe = [
            new \App\Filters\Request\Id('contract', $request)
        ];
        return Pipeline::send('where contract.id is not null')
            ->through($pipe)
            ->thenReturn();
    }
}
