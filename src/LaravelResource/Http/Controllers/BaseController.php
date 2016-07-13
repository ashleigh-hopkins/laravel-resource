<?php namespace LaravelResource\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as IlluminateController;
use LaravelResource\Transformers\PaginatorTransformer;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

abstract class BaseController extends IlluminateController
{
    /**
     * @var \Illuminate\Contracts\Pagination\Paginator|\Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    protected $paginator;

    protected $statusCode = SymfonyResponse::HTTP_OK;

    /**
     * @param $data
     * @param array $headers
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respond($data, $headers = [])
    {
        if ($this->paginator) {
            $data['meta'] = app(PaginatorTransformer::class)->item($this->paginator);
        }

        if (\App::environment('production') == false) {
            $queries = \DB::getQueryLog();
            if ($queries) {
                $data += ['queries' => $queries];
            }

            if (class_exists('RestModel\Database\Rest\Client')) {
                $requests = \RestModel\Database\Rest\Client::getRequestLog();

                if ($requests) {
                    $data += ['restRequests' => $requests];
                }
            }
        }

        return new JsonResponse((object)$data, $this->statusCode, $headers);
    }

    /**
     * @param $data
     * @param array $headers
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondCreated($data, $headers = [])
    {
        return $this->setStatusCode(SymfonyResponse::HTTP_CREATED)->respondWithData($data, $headers);
    }

    /**
     * @param array $error
     * @param array $headers
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondInternalError($error = ['http' => ['error.http.500']], $headers = [])
    {
        return $this->setStatusCode(SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR)->respondWithError($error, $headers);
    }

    /**
     * @param array $error
     * @param array $headers
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondBadRequest($error = ['http' => ['error.http.400']], $headers = [])
    {
        return $this->setStatusCode(SymfonyResponse::HTTP_BAD_REQUEST)->respondWithError($error, $headers);
    }

    /**
     * @param array $error
     * @param array $headers
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondUnauthorized($error = ['http' => ['error.http.401']], $headers = [])
    {
        return $this->setStatusCode(SymfonyResponse::HTTP_UNAUTHORIZED)->respondWithError($error, $headers);
    }

    /**
     * @param array $headers
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondNoContent($headers = [])
    {
        return $this->setStatusCode(SymfonyResponse::HTTP_NO_CONTENT)->respondWithData(null, $headers);
    }

    /**
     * @param array $error
     * @param array $headers
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondForbidden($error = ['http' => ['error.http.403']], $headers = [])
    {
        return $this->setStatusCode(SymfonyResponse::HTTP_FORBIDDEN)->respondWithError($error, $headers);
    }

    /**
     * @param array $error
     * @param array $headers
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondNotFound($error = ['http' => ['error.http.404']], $headers = [])
    {
        return $this->setStatusCode(SymfonyResponse::HTTP_NOT_FOUND)->respondWithError($error, $headers);
    }

    /**
     * @param array $headers
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondNotModified($headers = [])
    {
        return $this->setStatusCode(SymfonyResponse::HTTP_NOT_MODIFIED)->respondWithData(null, $headers);
    }

    /**
     * @param $error
     * @param array $headers
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondMethodNotAllowed($error = ['http' => ['error.http.405']], $headers = [])
    {
        return $this->setStatusCode(SymfonyResponse::HTTP_METHOD_NOT_ALLOWED)->respondWithError($error, $headers);
    }

    /**
     * @param array $error
     * @param array $headers
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondModelNotFound($error = ['model' => ['error.model.not-found']], $headers = [])
    {
        return $this->setStatusCode(SymfonyResponse::HTTP_NOT_FOUND)->respondWithError($error, $headers);
    }

    /**
     * @param array $error
     * @param array $headers
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondPreconditionFailed($error = ['http' => ['error.http.412']], $headers = [])
    {
        return $this->setStatusCode(SymfonyResponse::HTTP_PRECONDITION_FAILED)->respondWithError($error, $headers);
    }

    /**
     * @param $error
     * @param array $headers
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondUnprocessableEntity($error = ['http' => ['error.http.422']], $headers = [])
    {
        return $this->setStatusCode(SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY)->respondWithError($error, $headers);
    }

    /**
     * @param $data
     * @param array $headers
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondSuccess($data = null, $headers = [])
    {
        return $this->setStatusCode(SymfonyResponse::HTTP_OK)->respondWithData($data, $headers);
    }

    /**
     * @param $data
     * @param array $headers
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithData($data, $headers = [])
    {
        $data = ($data === null || $data === []) ? [] : ['data' => $data];

        return $this->respond($data, $headers);
    }

    /**
     * @param $error
     * @param array $headers
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithError($error, $headers = [])
    {
        return $this->respond([
            'error' => $error
        ], $headers);
    }

    /**
     * @param \Illuminate\Contracts\Pagination\Paginator|\Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator
     * @return $this
     */
    protected function setPaginator($paginator)
    {
        $this->paginator = $paginator;

        return $this;
    }

    /**
     * @param int $statusCode
     * @return $this
     */
    protected function setStatusCode($statusCode)
    {
        $this->statusCode = $statusCode;

        return $this;
    }
}
