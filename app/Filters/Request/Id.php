<?php

namespace App\Filters\Request;

class Id
{
    public function __invoke($table, $request, \Closure $next)
    {
        return $next()->when(
            $request->has('id'),
            fn () =>
            " and " . $table . ".id = '" . $request->id . "'"
        );
    }
}
