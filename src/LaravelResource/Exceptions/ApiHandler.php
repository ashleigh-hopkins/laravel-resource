<?php namespace LaravelResource\Exceptions;

use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Foundation\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

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
     * @param  \Exception  $e
     * @return void
     */
    public function report(Exception $e)
    {
        parent::report($e);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $e
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $e)
    {
        if($request->isJson() || $request->wantsJson() || starts_with($request->path(), 'api'))
        {
            if($this->isModelNotFoundException($e))
            {
                $data = ['error' => ['model' => ['error.model.404']]];

                if(app('app')->environment('production') == false)
                {
                    $data['context'] = ['model' => $e->getModel()];
                    $data['queries'] = \DB::getQueryLog();

                    if(class_exists('RestModel\Database\Rest\Client'))
                    {
                        $requests = \RestModel\Database\Rest\Client::getRequestLog();

                        if($requests)
                        {
                            $data += ['restRequests' => $requests];
                        }
                    }
                }

                return \Response::json($data, 404);
            }
            else
            {
                $code = 500;

                if($this->isHttpException($e))
                {
                    $code = $e->getStatusCode();
                }

                $data = ['error' => ['http' => ['error.http.' . $code]]];

                if(app('app')->environment('production') == false)
                {
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

                    if(class_exists('RestModel\Database\Rest\Client'))
                    {
                        $requests = \RestModel\Database\Rest\Client::getRequestLog();

                        if($requests)
                        {
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
}
