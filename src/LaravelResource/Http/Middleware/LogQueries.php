<?php namespace LaravelResource\Http\Middleware;

class LogQueries
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, \Closure $next)
    {
        \DB::enableQueryLog();

        return $next($request);
    }
}
