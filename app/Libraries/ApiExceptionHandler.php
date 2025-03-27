<?php
namespace App\Libraries;

use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

class ApiExceptionHandler
{
    use ResponseTrait;

    public function handle(Throwable $exception, RequestInterface $request): ResponseInterface
    {
        $statusCode = $this->determineStatusCode($exception);

        return $this->respond([
            'success' => false,
            'message' => $exception->getMessage(),
            'data'    => null,
        ], $statusCode);
    }

    protected function determineStatusCode(Throwable $exception): int
    {
        $statusCode = $exception->getCode();

        if ($statusCode < 100 || $statusCode >= 600) {
            $statusCode = 500;
        }

        return $statusCode;
    }
}
