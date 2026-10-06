<?php

namespace Ernestdefoe\Sonic\Api\Controller;

use Ernestdefoe\Sonic\Job\RebuildJob;
use Ernestdefoe\Sonic\Sonic;
use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Admin only. Answers with ok, an error code, and how many objects each index
 * holds for this forum — never the host, password or Sonic's own output.
 */
class StatusController implements RequestHandlerInterface
{
    public function __construct(
        protected Sonic $sonic
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        return new JsonResponse($this->sonic->status(array_map(fn ($class) => $class::index(), RebuildJob::INDEXERS)));
    }
}
