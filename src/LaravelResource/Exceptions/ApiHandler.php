<?php namespace LaravelResource\Exceptions;

use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Foundation\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ApiHandler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception $e
     * @return void
     */
    public function report(Exception $e)
    {
        parent::report($e);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Exception $e
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $e)
    {
        if ($request->isJson() || $request->wantsJson() || starts_with($request->path(), 'api')) {

            if ($this->isModelNotFoundException($e)) {

                $data = ['error' => ['model' => ['error.model.404']]];

                if (app('app')->environment('production') == false) {

                    $data['context'] = ['model' => $e->getModel()];
                    $data['queries'] = \DB::getQueryLog();

                    if (class_exists('RestModel\Database\Rest\Client')) {

                        $requests = \RestModel\Database\Rest\Client::getRequestLog();

                        if ($requests) {
                            $data += ['restRequests' => $requests];
                        }
                    }
                }

                return \Response::json($data, 404);

            } else {

                $code = 500;

                if ($this->isHttpException($e)) {
                    $code = $e->getStatusCode();
                }

                $data = ['error' => ['http' => ['error.http.' . $code]]];

                // don't report "internal server errors" to client

                if ($code != 500) {

                    $isAuthError = $this->isJwtAuthException($e);

                    if ($isAuthError) {
                        $data['error'] = ['auth' => [$e->getMessage()]];
                    } else {
                        $data['error'] = ['message' => [$e->getMessage()]];
                    }
                }

                if (app()->environment('production') == false) {

                    $data['context'] = [
                        'exception' => [
                            'code' => $e->getCode(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'message' => $e->getMessage(),
                            'trace' => $e->getTrace(),
                        ]
                    ];

                    $data['queries'] = \DB::getQueryLog();

                    if (class_exists('RestModel\Database\Rest\Client')) {
                        $requests = \RestModel\Database\Rest\Client::getRequestLog();

                        if ($requests) {
                            $data += ['restRequests' => $requests];
                        }
                    }
                }

                return \Response::json($data, $code, [], JSON_PARTIAL_OUTPUT_ON_ERROR);
            }
        }

        return parent::render($request, $e);
    }

    private function isModelNotFoundException(Exception $e)
    {
        return $e instanceof ModelNotFoundException;
    }

    /**
     * @param Exception $e
     * @return bool
     */
    private function isJwtAuthException(Exception $e)
    {
        $trace = $e->getTrace();

        // check if the class that sent us here was the Tymon Authenticate middleware
        
        for ($i = 0; $i < 3; $i++) {
            if (isset($trace[$i]) == false) {
                break;
            }
            if (isset($trace[$i]) && data_get($trace, "{$i}.class") == 'Tymon\JWTAuth\Http\Middleware\Authenticate') {
                return true;
            }
        }

        return false;
    }
}
