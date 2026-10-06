<?php

namespace Ernestdefoe\Sonic\Api\Controller;

use Ernestdefoe\Sonic\Job\RebuildJob;
use Ernestdefoe\Sonic\Sonic;
use Flarum\Http\RequestUtil;
use Illuminate\Contracts\Queue\Queue;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RebuildController implements RequestHandlerInterface
{
    public function __construct(
        protected Sonic $sonic,
        protected Queue $queue
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        if (! $this->sonic->configured()) {
            return new JsonResponse(['error' => 'not_configured'], 422);
        }

        $this->queue->push(new RebuildJob());

        return new EmptyResponse(202);
    }
}
